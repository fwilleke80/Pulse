<?php

/**
 * @file BackupRestoreService.php
 * @brief Staged complete-system restore with rollback snapshot and maintenance lock.
 */

declare(strict_types=1);

namespace Pulse\Services;

use PDO;
use PharData;
use Pulse\Core\EnvironmentFile;
use Pulse\Core\MigrationRunner;
use Pulse\Core\Session;
use RuntimeException;
use Throwable;

/** @brief Restores verified same-version backups while suspending normal Pulse activity. */
final class BackupRestoreService
{
	private BackupService $_backup;
	private PDO $_connection;
	private string $_root;
	private EnvironmentFile $_environment;
	private Session $_session;

	/** @brief Constructs the recovery service. */
	public function __construct(BackupService $backup, PDO $connection, string $root, EnvironmentFile $environment, Session $session)
	{
		$this->_backup = $backup;
		$this->_connection = $connection;
		$this->_root = rtrim($root, '/');
		$this->_environment = $environment;
		$this->_session = $session;
	}

	/**
	 * @brief Check a backup and recovery environment without replacing current data.
	 * @param string $uploaded Uploaded or privately staged backup archive.
	 * @param string $password Optional archive decryption password.
	 * @param string $runningVersion Exact installed application version.
	 * @return array<string, mixed> Restore preview metadata and checks.
	 */
	public function Preflight(string $uploaded, string $password, string $runningVersion): array
	{
		$work = $this->_backup->TemporaryDirectory();
		try
		{
			$plain = $work . '/verified.zip';
			$manifest = $this->_backup->OpenAndVerify($uploaded, $password, $plain);
			if (($manifest['pulse_version'] ?? '') !== $runningVersion)
			{
				throw new RuntimeException('Backup version must exactly match installed Pulse version.');
			}
			$archive = new PharData($plain);
			$sql = $archive['database/pulse.sql']->getContent();
			if (strlen($sql) > 134217728)
			{
				throw new RuntimeException('Database dump exceeds the browser restore limit.');
			}
			$this->ValidateSql($sql);
			$backedEnvironment = $work . '/backed-env';
			file_put_contents($backedEnvironment, $archive['config/.env']->getContent());
			$settings = (new EnvironmentFile($backedEnvironment))->ReadValues();
			if (!isset($settings['PULSE_TOTP_ENCRYPTION_KEY']))
			{
				throw new RuntimeException('Backup is missing its TOTP encryption key.');
			}
			$uploadRoot = $this->_root . '/storage/uploads';
			foreach ([$this->_root . '/storage', $this->_root . '/storage/rollbacks', $uploadRoot] as $directory)
			{
				if (is_dir($directory) && (is_link($directory) || !is_writable($directory)))
				{
					throw new RuntimeException('Restore destination is not writable: ' . $directory);
				}
			}
			if (!is_file($this->_environment->Path()) || !is_writable($this->_environment->Path()))
			{
				throw new RuntimeException('Installation configuration is not writable.');
			}
			if (is_file($this->_root . '/storage/maintenance.lock'))
			{
				throw new RuntimeException('Pulse is already in maintenance mode.');
			}
			$bytes = array_sum(array_column($manifest['files'], 'size'));
			$minimum = max(67108864, $bytes * 3 + (int)filesize($uploaded));
			$free = disk_free_space($this->_root . '/storage');
			if ($free === false || $free < $minimum)
			{
				throw new RuntimeException('Insufficient space for staged restore and preliminary recovery backup.');
			}
			// Basic permission introspection is advisory: database roles may be inherited.
			$grants = $this->_connection->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
			$grantText = strtoupper(implode("\n", array_map('strval', $grants)));
			$grantWarning = false;
			foreach (['CREATE', 'DROP', 'INSERT', 'SELECT'] as $requiredGrant)
			{
				if (!str_contains($grantText, 'ALL PRIVILEGES') && !str_contains($grantText, $requiredGrant))
				{
					$grantWarning = true;
				}
			}
			return [
				'created_at' => (string)($manifest['created_at'] ?? ''),
				'pulse_version' => $runningVersion,
				'file_count' => count($manifest['files']) - 2,
				'package_sha256' => hash_file('sha256', $uploaded),
				'checks' => ['Backup checksum manifest', 'Safe SQL import format', 'Pulse version and TOTP secret', 'Restoration paths', 'Initial free disk space', 'Database access'],
				'grant_warning' => $grantWarning,
			];
		}
		finally
		{
			$this->_backup->DeleteDirectory($work);
		}
	}

	/**
	 * @brief Validates and restores a full backup, leaving the installation paused for owner review.
	 * @param string $uploaded Full backup ZIP path.
	 * @param string $password Optional archive password.
	 * @param string $runningVersion Installed Pulse version, required to match the backup.
	 * @return array<string, mixed> Verified backup manifest.
	 */
	public function Restore(string $uploaded, string $password, string $runningVersion): array
	{
		$work = $this->_backup->TemporaryDirectory();
		$flag = $this->_root . '/storage/maintenance.lock';
		$locked = false;
		$operationGate = null;
		$databaseTouched = false;
		$originalEnv = [];
		$backupRollback = '';
		$uploadsMoved = false;
		$originalUploads = $this->_root . '/storage/uploads';
		$oldUploads = $work . '/old-uploads';

		try
		{
			$stagedUpload = $work . '/uploaded.zip';
			if (!copy($uploaded, $stagedUpload))
			{
				throw new RuntimeException('Could not stage uploaded backup.');
			}
			$uploaded = $stagedUpload;
			$plain = $work . '/verified.zip';
			$manifest = $this->_backup->OpenAndVerify($uploaded, $password, $plain);
			if (($manifest['pulse_version'] ?? '') !== $runningVersion)
			{
				throw new RuntimeException('Backup and installed Pulse versions must match for automatic restore.');
			}
			$archive = new PharData($plain);
			$sql = $archive['database/pulse.sql']->getContent();
			if (strlen($sql) > 134217728)
			{
				throw new RuntimeException('This database dump is too large for browser-based restore.');
			}
			$statements = $this->ValidateSql($sql);
			unset($sql);
			$stagedUploads = $work . '/uploads';
			if (!mkdir($stagedUploads, 0700))
			{
				throw new RuntimeException('Cannot stage restored files.');
			}
			foreach ($manifest['files'] as $name => $metadata)
			{
				if (!str_starts_with($name, 'storage/uploads/'))
				{
					continue;
				}
				$relative = substr($name, strlen('storage/uploads/'));
				$target = $stagedUploads . '/' . $relative;
				$directory = dirname($target);
				if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory))
				{
					throw new RuntimeException('Could not stage restored upload directory.');
				}
				$input = fopen($archive[$name]->getPathname(), 'rb');
				$output = fopen($target, 'xb');
				if ($input === false || $output === false)
				{
					throw new RuntimeException('Could not copy backed-up file.');
				}
				try
				{
					$copied = stream_copy_to_stream($input, $output);
					if ($copied === false || $copied !== (int)$metadata['size'])
					{
						throw new RuntimeException('Incomplete file during restore.');
					}
				}
				finally
				{
					fclose($input);
					fclose($output);
				}
			}

			$backedEnvironment = $work . '/backed-env';
			if (file_put_contents($backedEnvironment, $archive['config/.env']->getContent()) === false)
			{
				throw new RuntimeException('Could not read saved configuration.');
			}
			$restoredEnv = (new EnvironmentFile($backedEnvironment))->ReadValues();
			$originalEnv = $this->_environment->ReadValues();
			$restoredEnv = array_filter($restoredEnv, static fn (string $key): bool =>
				!str_starts_with($key, 'PULSE_DB_') && !in_array($key, ['PULSE_BASE_URL', 'PULSE_TRUSTED_HOSTS', 'PULSE_SESSION_NAME'], true), ARRAY_FILTER_USE_KEY);
			if (!array_key_exists('PULSE_TOTP_ENCRYPTION_KEY', $restoredEnv))
			{
				throw new RuntimeException('Backup is missing its TOTP encryption secret.');
			}

			// Exclusively create a lock file: other recovery operations must not overlap.
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
			fwrite($lock, json_encode(['reason' => 'restore', 'status' => 'in_progress', 'started_at' => gmdate('c')]));
			fclose($lock);
			@chmod($flag, 0600);
			$locked = true;

			$rollbackRoot = $this->_root . '/storage/rollbacks';
			if (!is_dir($rollbackRoot) && !mkdir($rollbackRoot, 0700, true))
			{
				throw new RuntimeException('Cannot create emergency recovery directory.');
			}
			$backupRollback = $rollbackRoot . '/pre-restore-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.zip';
			$this->_backup->Create($backupRollback, '', $runningVersion);
			@chmod($backupRollback, 0600);

			$databaseTouched = true;
			$this->ImportSql($statements);
			if (is_dir($originalUploads))
			{
				if (!rename($originalUploads, $oldUploads))
				{
					throw new RuntimeException('Could not move previous uploaded files.');
				}
				$uploadsMoved = true;
			}
			if (!rename($stagedUploads, $originalUploads))
			{
				throw new RuntimeException('Could not install restored uploaded files.');
			}
			$this->_environment->Update($restoredEnv);
			$this->_session->InvalidateAllAuthenticatedSessions();
			if (file_put_contents($flag, json_encode(['reason' => 'restore', 'status' => 'awaiting_review',
				'completed_at' => gmdate('c')]), LOCK_EX) === false)
			{
				throw new RuntimeException('Could not mark restoration ready for owner review.');
			}
			// Do not delete the maintenance lock. Owner review and explicit resume are mandatory.
			return $manifest;
		}
		catch (Throwable $error)
		{
			if ($databaseTouched && $backupRollback !== '' && is_file($backupRollback))
			{
				try
				{
					$rollback = new PharData($backupRollback);
					$this->ImportSql($this->ValidateSql($rollback['database/pulse.sql']->getContent(), true));
					if (!$uploadsMoved && is_dir($originalUploads))
					{
						$this->RemoveFiles($originalUploads);
					}
					if ($uploadsMoved)
					{
						if (is_dir($originalUploads))
						{
							$this->RemoveFiles($originalUploads);
						}
						rename($oldUploads, $originalUploads);
						}
					if ($originalEnv !== [])
					{
						$this->_environment->Update($originalEnv);
					}
					if ($locked)
					{
						unlink($flag);
					}
				}
				catch (Throwable $rollbackFailure)
				{
					throw new RuntimeException('Restore failed and automatic rollback failed. Maintenance remains active. Recover from ' . $backupRollback, 0, $rollbackFailure);
				}
			}
			else if ($locked)
			{
				unlink($flag);
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

	/** @brief Leaves recovery mode only after the newly authenticated administrator approves. */
	public function Resume(): void
	{
		$flag = $this->_root . '/storage/maintenance.lock';
		$state = is_file($flag) ? json_decode((string)file_get_contents($flag), true) : null;
		if (!is_array($state) || ($state['reason'] ?? '') !== 'restore'
			|| ($state['status'] ?? '') !== 'awaiting_review' || !unlink($flag))
		{
			throw new RuntimeException('No resumable maintenance state was found.');
		}
	}

	/** @brief Returns whether monitoring and recipient mail delivery are paused. */
	public function IsPaused(): bool
	{
		return is_file($this->_root . '/storage/maintenance.lock');
	}

	/** @brief Rejects SQL not matching Pulse's constrained table-only dump format before any writes. */
	private function ValidateSql(string $sql, bool $allowMissingTables = false): array
	{
		$statements = MigrationRunner::SplitStatements($sql);
		$allowed = [
			'/^SET NAMES utf8mb4$/i',
			'/^SET FOREIGN_KEY_CHECKS=[01]$/i',
			'/^DROP TABLE IF EXISTS `[^`]+`$/i',
			'/^CREATE TABLE `[^`]+`\\s*\\(/is',
			'/^INSERT INTO `[^`]+`\\s*\\(/is',
		];
		$existing = $this->_connection->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
		$allowedTables = array_fill_keys(array_map(static fn (array $row): string => (string)$row[0], $existing), true);
		foreach ($statements as $statement)
		{
			$valid = false;
			foreach ($allowed as $regex)
			{
				if (preg_match($regex, $statement) === 1)
				{
					$valid = true;
					break;
				}
			}
			if (!$valid)
			{
				throw new RuntimeException('Backup contains unsupported SQL statements.');
			}
			if (preg_match('/^(?:DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO) `([^`]+)`/i', $statement, $match) === 1
				&& !$allowMissingTables && !isset($allowedTables[$match[1]]))
			{
				throw new RuntimeException('Backup contains an unknown database table.');
			}
		}
		return $statements;
	}

	/** @brief Executes previously validated SQL against the configured destination database. */
	private function ImportSql(array $statements): void
	{
		try
		{
			$this->_connection->exec('SET FOREIGN_KEY_CHECKS=0');
			foreach ($statements as $statement)
			{
				$this->_connection->exec($statement);
			}
		}
		finally
		{
			$this->_connection->exec('SET FOREIGN_KEY_CHECKS=1');
		}
	}

	/** @brief Deletes only paths in private upload staging/rotation. */
	private function RemoveFiles(string $path): void
	{
		if (!str_starts_with($path, $this->_root . '/storage/uploads') || !is_dir($path))
		{
			throw new RuntimeException('Invalid rollback target.');
		}
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iterator as $item)
		{
			if ($item->isDir())
			{
				rmdir($item->getPathname());
			}
			else
			{
				unlink($item->getPathname());
			}
		}
		rmdir($path);
	}
}
