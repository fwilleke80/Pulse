<?php

/**
 * @file UpdatePackageInspector.php
 * @brief Read-only validation and preflight of versioned Pulse update archives.
 */

declare(strict_types=1);

namespace Pulse\Services;

use PharData;
use RecursiveIteratorIterator;
use RuntimeException;

/** @brief Checks complete package inventory, migration safety, installation permissions, and release notes. */
final class UpdatePackageInspector
{
	private const MANIFEST_PATH = 'manifest.json';
	private const LEGACY_MANIFEST_PATH = 'docs/update-manifest.json';
	private string $_root;

	/** @param string $root Absolute Pulse installation root. */
	public function __construct(string $root)
	{
		$this->_root = rtrim($root, '/');
	}

	/**
	 * @brief Validate an update before any installed file is changed.
	 * @param string $source Uploaded or privately staged ZIP.
	 * @param string $installedVersion Currently installed version.
	 * @return array<string, mixed> Validated package metadata and release notes.
	 */
	public function Inspect(string $source, string $installedVersion): array
	{
		if (!is_file($source) || !is_readable($source) || (int)filesize($source) > 268435456)
		{
			throw new RuntimeException('Update file is missing, unreadable, or exceeds the 256 MiB limit.');
		}
		$archive = new PharData($source);
		$entries = [];
		$totalBytes = 0;
		foreach (new RecursiveIteratorIterator($archive) as $item)
		{
			$path = substr($item->getPathname(), strlen('phar://' . $source . '/'));
			if (!$item->isFile() || $item->isLink() || !UpdateInstallerService::AllowedPackageFile($path) || isset($entries[$path]))
			{
				throw new RuntimeException('Unsafe or duplicate update package member: ' . $path);
			}
			$totalBytes += $item->getSize();
			if ($item->getSize() > 67108864 || $totalBytes > 536870912 || count($entries) >= 10000)
			{
				throw new RuntimeException('Update package exceeds extraction limits.');
			}
			$entries[$path] = $item->getPathname();
		}
		// The canonical manifest is root-level from 1.4.2. The earlier location
		// is accepted only for previously produced packages.
		$manifestPath = isset($entries[self::MANIFEST_PATH]) ? self::MANIFEST_PATH : self::LEGACY_MANIFEST_PATH;
		$manifestPaths = isset($entries[self::MANIFEST_PATH])
			? [self::MANIFEST_PATH, ...(isset($entries[self::LEGACY_MANIFEST_PATH]) ? [self::LEGACY_MANIFEST_PATH] : [])]
			: [self::LEGACY_MANIFEST_PATH];
		foreach (['bootstrap.php', 'public/index.php', 'config/version.php', 'database/schema.sql', 'CHANGELOG.md', $manifestPath] as $required)
		{
			if (!isset($entries[$required]))
			{
				throw new RuntimeException('Incomplete update: required member missing: ' . $required);
			}
		}
		$manifest = json_decode((string)file_get_contents($entries[$manifestPath]), true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($manifest) || ($manifest['format'] ?? null) !== 'pulse-update-v1'
			|| !is_array($manifest['files'] ?? null) || !is_array($manifest['migrations'] ?? null))
		{
			throw new RuntimeException('Update manifest format is invalid.');
		}
		$versionSource = (string)file_get_contents($entries['config/version.php']);
		if (preg_match('/return\s+[\'\"](\d+\.\d+\.\d+)[\'\"]\s*;/', $versionSource, $match) !== 1)
		{
			throw new RuntimeException('Update version metadata is malformed.');
		}
		$newVersion = $match[1];
		if ($newVersion !== ($manifest['version'] ?? null) || version_compare($newVersion, $installedVersion, '<='))
		{
			throw new RuntimeException('Update version does not match the manifest or is not newer than the installed version.');
		}
		$inventory = $manifest['files'];
		if (count($inventory) !== count($entries) - count($manifestPaths))
		{
			throw new RuntimeException('Update manifest does not list every packaged file.');
		}
		foreach ($entries as $path => $member)
		{
			if (in_array($path, $manifestPaths, true))
			{
				continue;
			}
			$info = $inventory[$path] ?? null;
			if (!is_array($info) || !is_int($info['size'] ?? null)
				|| $info['size'] !== filesize($member) || !is_string($info['sha256'] ?? null)
				|| preg_match('/^[a-f0-9]{64}$/D', $info['sha256']) !== 1
				|| !hash_equals($info['sha256'], hash_file('sha256', $member)))
			{
				throw new RuntimeException('Update member is missing from the manifest or failed checksum: ' . $path);
			}
			if (str_starts_with($path, 'database/migrations/') && is_file($this->_root . '/' . $path)
				&& !hash_equals(hash_file('sha256', $this->_root . '/' . $path), $info['sha256']))
			{
				throw new RuntimeException('Update changes an already installed database migration: ' . $path);
			}
		}
		foreach ($inventory as $path => $info)
		{
			if (!is_string($path) || !isset($entries[$path]))
			{
				throw new RuntimeException('Update manifest contains unexpected file inventory entries.');
			}
		}
		// During 1.4.2 migration, the legacy manifest must validate the same
		// application inventory AND the root manifest's own checksum. This lets
		// the 1.4.1 updater check the new root member before installing it.
		if (count($manifestPaths) === 2)
		{
			$legacy = json_decode((string)file_get_contents($entries[self::LEGACY_MANIFEST_PATH]), true, 512, JSON_THROW_ON_ERROR);
			$rootMember = $entries[self::MANIFEST_PATH];
			$expectedFiles = $inventory;
			$expectedFiles[self::MANIFEST_PATH] = [
				'size' => filesize($rootMember),
				'sha256' => hash_file('sha256', $rootMember),
			];
			ksort($expectedFiles, SORT_STRING);
			if (!is_array($legacy) || ($legacy['format'] ?? null) !== $manifest['format']
				|| ($legacy['version'] ?? null) !== $manifest['version']
				|| ($legacy['migrations'] ?? null) !== $manifest['migrations']
				|| ($legacy['files'] ?? null) !== $expectedFiles)
			{
				throw new RuntimeException('Legacy update manifest is inconsistent with the root manifest.');
			}
		}
		$migrations = [];
		foreach ($entries as $path => $member)
		{
			if (preg_match('~^database/migrations/[^/]+\.sql$~', $path) === 1)
			{
				$migrations[$path] = $inventory[$path]['sha256'];
			}
		}
		ksort($migrations, SORT_STRING);
		if ($manifest['migrations'] !== $migrations)
		{
			throw new RuntimeException('Migration inventory does not match the package.');
		}
		if (PHP_VERSION_ID < 80400 || !extension_loaded('openssl') || !extension_loaded('pdo_mysql')
			|| !extension_loaded('phar'))
		{
			throw new RuntimeException('Required PHP 8.4 extensions or runtime are missing.');
		}
		if (is_file($this->_root . '/storage/maintenance.lock'))
		{
			throw new RuntimeException('Pulse is already in maintenance mode.');
		}
		foreach ($entries as $path => $member)
		{
			if (in_array($path, ['public/install.php', '.env.example'], true) || str_starts_with($path, 'storage/'))
			{
				continue;
			}
			$target = $this->_root . '/' . $path;
			$directory = dirname($target);
			while (!file_exists($directory) && $directory !== dirname($directory))
			{
				$directory = dirname($directory);
			}
			if (is_link($target) || !is_dir($directory) || !is_writable($directory)
				|| (file_exists($target) && (!is_file($target) || !is_readable($target) || !is_writable($target))))
			{
				throw new RuntimeException('Cannot safely replace update destination: ' . $path);
			}
			// Reject existing symlink components anywhere below the installation root.
			$components = explode('/', dirname($path));
			$prefix = $this->_root;
			foreach ($components as $component)
			{
				if ($component === '.')
				{
					continue;
				}
				$prefix .= '/' . $component;
				if (is_link($prefix))
				{
					throw new RuntimeException('Update destination contains a symbolic link: ' . $path);
				}
			}
		}
		foreach (['storage', 'storage/rollbacks'] as $path)
		{
			$directory = $this->_root . '/' . $path;
			if (is_dir($directory) && !is_writable($directory))
			{
				throw new RuntimeException('Recovery directory is not writable: ' . $path);
			}
		}
		$free = disk_free_space($this->_root . '/storage');
		$requiredSpace = max(67108864, $totalBytes * 3 + (int)filesize($source) * 2);
		if ($free === false || $free < $requiredSpace)
		{
			throw new RuntimeException('Insufficient free disk space for staging and initial rollback preparation.');
		}
		$changelog = (string)file_get_contents($entries['CHANGELOG.md']);
		$notes = self::ReleaseNotes($changelog, $installedVersion, $newVersion);
		return [
			'installed_version' => $installedVersion,
			'new_version' => $newVersion,
			'file_count' => count($inventory),
			'migrations' => array_keys(array_filter($migrations, fn (string $value, string $path): bool => !is_file($this->_root . '/' . $path), ARRAY_FILTER_USE_BOTH)),
			'package_sha256' => hash_file('sha256', $source),
			'available_bytes' => (int)$free,
			'notes' => $notes,
			'checks' => ['Manifest and complete file inventory', 'SHA-256 checksums', 'Version and migration compatibility', 'PHP runtime', 'Destination directories', 'Initial staging disk space'],
		];
	}

	/**
	 * @brief Extract changelog sections covering exactly the upgrade version interval.
	 * @return string Safe-to-render Markdown source, not HTML.
	 */
	public static function ReleaseNotes(string $changelog, string $installedVersion, string $newVersion): string
	{
		preg_match_all('/^## (\d+\.\d+\.\d+)\b[^\r\n]*\R(.*?)(?=^## \d+\.\d+\.\d+\b|\z)/ms', $changelog, $matches, PREG_SET_ORDER);
		$sections = [];
		foreach ($matches as $entry)
		{
			if (version_compare($entry[1], $installedVersion, '>') && version_compare($entry[1], $newVersion, '<='))
			{
				$sections[] = '## ' . $entry[1] . "\n\n" . trim($entry[2]);
			}
		}
		return implode("\n\n", $sections);
	}
}
