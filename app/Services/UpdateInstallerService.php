<?php

/**
 * @file UpdateInstallerService.php
 * @brief Administrator-initiated, staged, rollback-aware Pulse ZIP package installer.
 */

declare(strict_types=1);

namespace Pulse\Services;

use PDO;
use PharData;
use Pulse\Core\Database;
use Pulse\Core\MigrationRunner;
use RuntimeException;
use Throwable;

/** @brief Stages a trusted release archive and applies application files and migrations. */
final class UpdateInstallerService
{
	private BackupService $_backup;
	private Database $_database;
	private string $_root;

	/** @brief Constructs the in-place ZIP updater. */
	public function __construct(BackupService $backup, Database $database, string $root)
	{
		$this->_backup = $backup;
		$this->_database = $database;
		$this->_root = rtrim($root, '/');
	}

	/**
	 * @brief Installs a specifically approved Pulse ZIP update; no remote downloads are performed.
	 * @param string $uploaded Uploaded file from verified PHP multipart request.
	 * @param string $installedVersion Current version.
	 * @return array<string, mixed> Version and migrations installed.
	 */
	public function Install(string $uploaded, string $installedVersion): array
	{
		$work = $this->_backup->TemporaryDirectory();
		$flag = $this->_root . '/storage/maintenance.lock';
		$locked = false;
		$operationGate = null;
		$started = false;
		$oldFiles = [];
		$newFiles = [];
		$rollbackArchive = '';

		try
		{
			// Repeat preflight at installation time, not only when the administrator previews the package.
			(new UpdatePackageInspector($this->_root))->Inspect($uploaded, $installedVersion);
			$source = $work . '/uploaded.zip';
			if (!copy($uploaded, $source))
			{
				throw new RuntimeException('Unable to stage uploaded update.');
			}
			$archive = new PharData($source);
			$entries = [];
			foreach (new \RecursiveIteratorIterator($archive) as $item)
			{
				$path = substr($item->getPathname(), strlen('phar://' . $source . '/'));
				if (!$item->isFile() || $item->isLink() || !self::AllowedPackageFile($path))
				{
					throw new RuntimeException('Unsafe or unsupported update member: ' . $path);
				}
				$entries[$path] = $item->getPathname();
			}
			foreach (['bootstrap.php', 'public/index.php', 'config/version.php', 'database/schema.sql'] as $required)
			{
				if (!isset($entries[$required]))
				{
					throw new RuntimeException('Incomplete Pulse update package: ' . $required);
				}
			}
			$versionSource = file_get_contents($entries['config/version.php']);
			if (!is_string($versionSource) || preg_match('/return\\s+[\'\"](\\d+\\.\\d+\\.\\d+)[\'\"]\\s*;/', $versionSource, $match) !== 1)
			{
				throw new RuntimeException('Update version metadata is malformed.');
			}
			$newVersion = $match[1];
			if (version_compare($newVersion, $installedVersion, '<='))
			{
				throw new RuntimeException('Downgrades and reinstalling the same version are not permitted.');
			}
			foreach ($entries as $path => $member)
			{
				if (!str_starts_with($path, 'database/migrations/') || !is_file($this->_root . '/' . $path))
				{
					continue;
				}
				if (!hash_equals(hash_file('sha256', $this->_root . '/' . $path), hash_file('sha256', $member)))
				{
					throw new RuntimeException('Package attempts to modify a previously installed migration.');
				}
			}

			$stagedFiles = $work . '/new';
			$oldDirectory = $work . '/old';
			mkdir($stagedFiles, 0700);
			mkdir($oldDirectory, 0700);
			foreach ($entries as $path => $member)
			{
				// The installation endpoint and environment stay under operator control.
				if ($path === 'public/install.php' || $path === '.env.example' || str_starts_with($path, 'storage/'))
				{
					continue;
				}
				$target = $stagedFiles . '/' . $path;
				$parent = dirname($target);
				if (!is_dir($parent) && !mkdir($parent, 0700, true))
				{
					throw new RuntimeException('Cannot stage update directory.');
				}
				$input = fopen($member, 'rb');
				$output = fopen($target, 'xb');
				if ($input === false || $output === false)
				{
					throw new RuntimeException('Cannot stage update member.');
				}
				try
				{
					if (stream_copy_to_stream($input, $output) !== filesize($member))
					{
						throw new RuntimeException('Incomplete staged update file.');
					}
				}
				finally
				{
					fclose($input);
					fclose($output);
				}
			}

			$operationGate = @fopen($this->_root . '/storage/maintenance.operation', 'c');
			if ($operationGate === false || !flock($operationGate, LOCK_EX | LOCK_NB))
			{
				throw new RuntimeException('Other Pulse requests are active; retry maintenance after they finish.');
			}
			$lock = @fopen($flag, 'x');
			if ($lock === false)
			{
				throw new RuntimeException('Pulse is already in maintenance mode.');
			}
			fwrite($lock, json_encode(['reason' => 'update', 'started_at' => gmdate('c'), 'new_version' => $newVersion]));
			fclose($lock);
			@chmod($flag, 0600);
			$locked = true;
			$rollbackRoot = $this->_root . '/storage/rollbacks';
			if (!is_dir($rollbackRoot) && !mkdir($rollbackRoot, 0700, true))
			{
				throw new RuntimeException('Could not create rollback directory.');
			}
			$rollbackArchive = $rollbackRoot . '/pre-update-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.zip';
			$this->_backup->Create($rollbackArchive, '', $installedVersion);
			@chmod($rollbackArchive, 0600);

			foreach ($entries as $path => $member)
			{
				if ($path === 'public/install.php' || $path === '.env.example' || str_starts_with($path, 'storage/'))
				{
					continue;
				}
				$target = $this->_root . '/' . $path;
				if (is_link($target))
				{
					throw new RuntimeException('Existing application path is a symbolic link: ' . $path);
				}
				if (is_file($target))
				{
					$backupPath = $oldDirectory . '/' . $path;
					if (!is_dir(dirname($backupPath)) && !mkdir(dirname($backupPath), 0700, true))
					{
						throw new RuntimeException('Cannot stage previous application file.');
					}
					if (!copy($target, $backupPath))
					{
						throw new RuntimeException('Cannot preserve previous application file.');
					}
					$oldFiles[] = $path;
				}
				else
				{
					$newFiles[] = $path;
				}
			}

			$started = true;
			foreach ($entries as $path => $member)
			{
				if ($path === 'public/install.php' || $path === '.env.example' || str_starts_with($path, 'storage/'))
				{
					continue;
				}
				$target = $this->_root . '/' . $path;
				if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0750, true))
				{
					throw new RuntimeException('Cannot create application destination directory.');
				}
				$new = dirname($target) . '/.pulse-install-' . bin2hex(random_bytes(8));
				if (!copy($stagedFiles . '/' . $path, $new) || !rename($new, $target))
				{
					@unlink($new);
					throw new RuntimeException('Cannot replace application file: ' . $path);
				}
				@chmod($target, 0644);
				if (function_exists('opcache_invalidate'))
				{
					@opcache_invalidate($target, true);
				}
			}

			$migrations = (new MigrationRunner($this->_database, $this->_root . '/database/migrations'))->Migrate();
			if (!unlink($flag))
			{
				throw new RuntimeException('Update installed but could not end maintenance mode.');
			}
			$locked = false;
			return ['previous_version' => $installedVersion, 'installed_version' => $newVersion, 'migrations' => $migrations,
				'rollback_archive' => basename($rollbackArchive)];
		}
		catch (Throwable $error)
		{
			if ($started)
			{
				$rolledBackFiles = true;
				foreach ($oldFiles as $path)
				{
					if (!copy($work . '/old/' . $path, $this->_root . '/' . $path))
					{
						$rolledBackFiles = false;
					}
				}
				foreach ($newFiles as $path)
				{
					@unlink($this->_root . '/' . $path);
				}
				// Migration DDL may have committed; keep all jobs paused if application rollback needs manual database recovery.
				throw new RuntimeException('Update failed. Previous application files ' . ($rolledBackFiles ? 'restored' : 'could not be fully restored')
					. '. The maintenance lock was retained. Database recovery snapshot: ' . $rollbackArchive, 0, $error);
			}
			if ($locked)
			{
				@unlink($flag);
			}
			throw $error;
		}
		finally
		{
			if (is_resource($operationGate))
			{
				flock($operationGate, LOCK_UN);
				fclose($operationGate);
			}
			$this->_backup->DeleteDirectory($work);
		}
	}

	/** @brief Applies a strict whitelist to all uploaded release paths. */
	public static function AllowedPackageFile(string $path): bool
	{
		if ($path === '' || str_contains($path, "\\0") || str_contains($path, '\\') || str_starts_with($path, '/'))
		{
			return false;
		}
		$segments = explode('/', $path);
		foreach ($segments as $segment)
		{
			if ($segment === '' || $segment === '.' || $segment === '..')
			{
				return false;
			}
		}
		if ($path === '.env' || str_starts_with($path, '.git/')
			|| str_starts_with($path, 'vendor/') || str_starts_with($path, 'tests/'))
		{
			return false;
		}
		return in_array($path, ['storage/.htaccess', 'storage/logs/.gitkeep', 'bootstrap.php', 'composer.json', 'README.md', 'CHANGELOG.md', 'manifest.json', '.htaccess', '.env.example', '.gitignore', '.editorconfig',
			'.php-cs-fixer.dist.php', 'phpstan.neon.dist', 'phpunit.xml', 'public/install.php'], true)
			|| preg_match('~^(app|config|database|docs|public|tools)/[a-zA-Z0-9_. /+-]+$~D', $path) === 1;
	}
}
