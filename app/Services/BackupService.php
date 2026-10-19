<?php

/**
 * @file BackupService.php
 * @brief Portable database and private-file archives for Pulse recovery.
 */

declare(strict_types=1);

namespace Pulse\Services;

use FilesystemIterator;
use PDO;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/** @brief Builds and checks complete, optionally password-encrypted Pulse backups. */
final class BackupService
{
	private const FORMAT = 'pulse-full-backup-v1';
	private const ENCRYPTION_FORMAT = 'pulse-aes-256-gcm-chunks-v1';
	private const CHUNK_SIZE = 1048576;
	private const PBKDF_ITERATIONS = 350000;

	private PDO $_connection;
	private string $_root;

	/** @brief Constructs the service. @param PDO $connection Database connection. @param string $root Absolute Pulse project root. */
	public function __construct(PDO $connection, string $root)
	{
		$this->_connection = $connection;
		$this->_root = rtrim($root, '/');
	}

	/**
	 * @brief Creates a full backup; the password is never persisted.
	 * @param string $destination New destination .zip path.
	 * @param string $password Optional encryption password; empty means plaintext.
	 * @param string $version Current Pulse application version.
	 * @return array<string, mixed> Backup manifest.
	 */
	public function Create(string $destination, string $password, string $version): array
	{
		if (file_exists($destination))
		{
			throw new RuntimeException('Destination archive already exists.');
		}

		$work = $this->TemporaryDirectory();

		try
		{
			$sqlPath = $work . '/database.sql';
			$this->DumpSql($sqlPath);
			$plainZip = $work . '/content.zip';
			$zip = new PharData($plainZip, 0, null, Phar::ZIP);
			$manifest = [
				'format' => self::FORMAT,
				'created_at' => gmdate('c'),
				'pulse_version' => $version,
				'files' => [],
			];
			$this->AddFile($zip, $manifest, $sqlPath, 'database/pulse.sql');
			$envPath = $this->_root . '/.env';

			if (!is_file($envPath) || !is_readable($envPath))
			{
				throw new RuntimeException('Installation .env is not readable. The backup would be incomplete.');
			}

			$this->AddFile($zip, $manifest, $envPath, 'config/.env');
			$uploadRoot = $this->_root . '/storage/uploads';
			if (is_dir($uploadRoot))
			{
				$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadRoot, FilesystemIterator::SKIP_DOTS));
				foreach ($files as $file)
				{
				if ($file->isLink())
				{
					throw new RuntimeException('Symbolic links are not allowed in private upload storage.');
				}
				if (!$file->isFile())
				{
					continue;
				}
				$relative = substr($file->getPathname(), strlen($uploadRoot) + 1);
				$this->AddFile($zip, $manifest, $file->getPathname(), 'storage/uploads/' . str_replace('\\', '/', $relative));
				}
			}

			$zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
			unset($zip);

			if ($password === '')
			{
				if (!rename($plainZip, $destination))
				{
					throw new RuntimeException('Could not move backup archive into place.');
				}
			}
			else
			{
				$outer = new PharData($destination, 0, null, Phar::ZIP);
				$secret = $work . '/encrypted.bin';
				$metadata = $this->Encrypt($plainZip, $secret, $password);
				$outer->addFromString('encryption.json', json_encode($metadata, JSON_THROW_ON_ERROR));
				$outer->addFile($secret, 'payload.enc');
				unset($outer);
			}

			return $manifest;
		}
		finally
		{
			$this->DeleteDirectory($work);
		}
	}

	/**
	 * @brief Validates and decrypts a backup into a caller-managed temporary ZIP path.
	 * @param string $source Uploaded Pulse backup .zip.
	 * @param string $password Password for encrypted packages.
	 * @param string $destination Path to a new, validated plain ZIP archive.
	 * @return array<string, mixed> Verified manifest.
	 */
	public function OpenAndVerify(string $source, string $password, string $destination): array
	{
		$outer = new PharData($source);
		if (isset($outer['encryption.json']))
		{
			$info = json_decode($outer['encryption.json']->getContent(), true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($info) || $password === '' || !isset($outer['payload.enc']))
			{
				throw new RuntimeException('Backup password or encrypted payload is missing.');
			}
			$this->Decrypt($outer['payload.enc']->getPathname(), $destination, $password, $info);
		}
		else
		{
			if (!copy($source, $destination))
			{
				throw new RuntimeException('Could not stage the uploaded archive.');
			}
		}

		$archive = new PharData($destination);
		if (!isset($archive['manifest.json']))
		{
			throw new RuntimeException('Backup manifest is missing.');
		}
		$manifest = json_decode($archive['manifest.json']->getContent(), true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($manifest) || ($manifest['format'] ?? '') !== self::FORMAT || !is_array($manifest['files'] ?? null))
		{
			throw new RuntimeException('Unsupported Pulse backup format.');
		}
		if (count($manifest['files']) > 100000)
		{
			throw new RuntimeException('Backup archive has too many entries.');
		}
		$totalBytes = 0;
		$expected = ['manifest.json' => true];
		foreach ($manifest['files'] as $name => $entry)
		{
			if (!is_string($name) || !self::AllowedEntry($name) || !is_array($entry))
			{
				throw new RuntimeException('Unsafe backup archive member.');
			}
			$declaredSize = (int)($entry['size'] ?? -1);
			$totalBytes += $declaredSize;
			if ($declaredSize < 0 || $declaredSize > 2147483648 || $totalBytes > 21474836480)
			{
				throw new RuntimeException('Backup exceeds safe extraction limits.');
			}
			if (!isset($archive[$name]) || $archive[$name]->isLink() || !is_file($archive[$name]->getPathname()))
			{
				throw new RuntimeException('Missing backup member: ' . $name);
			}
			$path = $archive[$name]->getPathname();
			if (filesize($path) !== (int)($entry['size'] ?? -1)
				|| !hash_equals((string)($entry['sha256'] ?? ''), hash_file('sha256', $path)))
			{
				throw new RuntimeException('Backup checksum failure: ' . $name);
			}
			$expected[$name] = true;
		}
		if (!isset($expected['database/pulse.sql'], $expected['config/.env']))
		{
			throw new RuntimeException('Backup is missing the database or installation secrets.');
		}
		foreach (new RecursiveIteratorIterator($archive) as $item)
		{
			$path = substr($item->getPathname(), strlen('phar://' . $destination . '/'));
			if (!isset($expected[$path]))
			{
				throw new RuntimeException('Unexpected file in backup: ' . $path);
			}
		}
		return $manifest;
	}

	/** @brief Lists only allowed ZIP member locations, rejecting traversal and special characters. */
	public static function AllowedEntry(string $entry): bool
	{
		if ($entry === 'database/pulse.sql' || $entry === 'config/.env')
		{
			return true;
		}
		return preg_match('~^storage/uploads/(?:[a-zA-Z0-9_.-]+/)*[a-zA-Z0-9_.-]+$~D', $entry) === 1
			&& !str_contains($entry, '..') && !str_contains($entry, '/./');
	}

	/** @brief Dumps table definitions, all rows and schema migration records. */
	private function DumpSql(string $destination): void
	{
		$handle = fopen($destination, 'wb');
		if ($handle === false)
		{
			throw new RuntimeException('Could not create database dump.');
		}
		try
		{
			$this->_connection->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
			$this->_connection->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
			$rows = $this->_connection->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
			$tableNames = array_map(static fn (array $row): string => (string)$row[0], $rows);
			sort($tableNames, SORT_STRING);
			fwrite($handle, "-- Pulse complete database dump\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
			foreach ($tableNames as $name)
			{
				$table = '`' . str_replace('`', '``', $name) . '`';
				$definition = $this->_connection->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM);
				if (!is_array($definition) || !isset($definition[1]))
				{
					throw new RuntimeException('Could not read database table definition.');
				}
				fwrite($handle, 'DROP TABLE IF EXISTS ' . $table . ";\n" . $definition[1] . ";\n");
				$cursor = $this->_connection->query('SELECT * FROM ' . $table, PDO::FETCH_ASSOC);
				while (($row = $cursor->fetch(PDO::FETCH_ASSOC)) !== false)
				{
					$columns = [];
					$values = [];
					foreach ($row as $column => $value)
					{
						$columns[] = '`' . str_replace('`', '``', (string)$column) . '`';
						$values[] = $value === null ? 'NULL' : ((string)$value === '' ? "''" : '0x' . bin2hex((string)$value));
					}
					fwrite($handle, 'INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n");
				}
				$cursor->closeCursor();
			}
			fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
			$this->_connection->commit();
		}
		catch (Throwable $exception)
		{
			if ($this->_connection->inTransaction())
			{
				$this->_connection->rollBack();
			}
			throw $exception;
		}
		finally
		{
			fclose($handle);
		}
	}

	/** @brief Adds and hashes a regular file without loading its full contents into PHP memory. */
	private function AddFile(PharData $archive, array &$manifest, string $source, string $name): void
	{
		if (!self::AllowedEntry($name) || !is_file($source) || is_link($source) || !is_readable($source))
		{
			throw new RuntimeException('Invalid or unreadable private backup file: ' . $name);
		}
		$size = filesize($source);
		if (!is_int($size) || $size < 0)
		{
			throw new RuntimeException('Could not determine backup file size.');
		}
		$archive->addFile($source, $name);
		$manifest['files'][$name] = ['size' => $size, 'sha256' => hash_file('sha256', $source)];
	}

	/**
	 * @brief Encrypts an inner ZIP with AES-256-GCM in authenticated independent chunks.
	 * @return array<string, mixed> Nonsecret encryption descriptor.
	 */
	private function Encrypt(string $source, string $destination, string $password): array
	{
		$salt = random_bytes(16);
		$nonceBase = random_bytes(8);
		$key = hash_pbkdf2('sha256', $password, $salt, self::PBKDF_ITERATIONS, 32, true);
		$input = fopen($source, 'rb');
		$output = fopen($destination, 'wb');
		if ($input === false || $output === false)
		{
			throw new RuntimeException('Cannot open encryption streams.');
		}
		try
		{
			$index = 0;
			while (!feof($input))
			{
				$plaintext = fread($input, self::CHUNK_SIZE);
				if ($plaintext === false)
				{
					throw new RuntimeException('Could not read backup plaintext.');
				}
				if ($plaintext === '')
				{
					break;
				}
				$nonce = $nonceBase . pack('N', $index);
				$tag = '';
				$ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, pack('N', $index), 16);
				if (!is_string($ciphertext))
				{
					throw new RuntimeException('Backup encryption failed.');
				}
				fwrite($output, pack('N', strlen($ciphertext)) . $tag . $ciphertext);
				$index++;
				if ($index >= 4294967295)
				{
					throw new RuntimeException('Backup is too large.');
				}
			}
		}
		finally
		{
			fclose($input);
			fclose($output);
		}
		return [
			'format' => self::ENCRYPTION_FORMAT,
			'iterations' => self::PBKDF_ITERATIONS,
			'salt' => base64_encode($salt),
			'nonce_base' => base64_encode($nonceBase),
			'chunk_size' => self::CHUNK_SIZE,
		];
	}

	/** @brief Decrypts and authenticates every chunk before exposing the inner archive. */
	private function Decrypt(string $source, string $destination, string $password, array $descriptor): void
	{
		if (($descriptor['format'] ?? '') !== self::ENCRYPTION_FORMAT
			|| ($descriptor['iterations'] ?? 0) !== self::PBKDF_ITERATIONS
			|| ($descriptor['chunk_size'] ?? 0) !== self::CHUNK_SIZE)
		{
			throw new RuntimeException('Unsupported backup encryption parameters.');
		}
		$salt = base64_decode((string)($descriptor['salt'] ?? ''), true);
		$nonceBase = base64_decode((string)($descriptor['nonce_base'] ?? ''), true);
		if (!is_string($salt) || strlen($salt) !== 16 || !is_string($nonceBase) || strlen($nonceBase) !== 8)
		{
			throw new RuntimeException('Malformed backup encryption parameters.');
		}
		$key = hash_pbkdf2('sha256', $password, $salt, self::PBKDF_ITERATIONS, 32, true);
		$input = fopen($source, 'rb');
		$output = fopen($destination, 'wb');
		if ($input === false || $output === false)
		{
			throw new RuntimeException('Could not create decryption streams.');
		}
		try
		{
			$index = 0;
			while (!feof($input))
			{
				$length = fread($input, 4);
				if ($length === '')
				{
					break;
				}
				if (!is_string($length) || strlen($length) !== 4)
				{
					throw new RuntimeException('Incomplete encrypted backup block.');
				}
				$size = unpack('N', $length)[1];
				if ($size <= 0 || $size > self::CHUNK_SIZE)
				{
					throw new RuntimeException('Invalid encrypted backup block size.');
				}
				$data = '';
				while (strlen($data) < $size + 16)
				{
					$piece = fread($input, $size + 16 - strlen($data));
					if ($piece === false || $piece === '')
					{
						throw new RuntimeException('Truncated encrypted backup block.');
					}
					$data .= $piece;
				}
				$plaintext = openssl_decrypt(substr($data, 16), 'aes-256-gcm', $key, OPENSSL_RAW_DATA,
					$nonceBase . pack('N', $index), substr($data, 0, 16), pack('N', $index));
				if (!is_string($plaintext))
				{
					throw new RuntimeException('Incorrect password or damaged backup archive.');
				}
				fwrite($output, $plaintext);
				$index++;
			}
		}
		finally
		{
			fclose($input);
			fclose($output);
		}
	}

	/** @brief Creates a private temporary working directory outside the document root. */
	public function TemporaryDirectory(): string
	{
		$parent = $this->_root . '/storage';
		$path = $parent . '/backup-' . bin2hex(random_bytes(16));
		if (!mkdir($path, 0700))
		{
			throw new RuntimeException('Could not create a private temporary directory.');
		}
		return $path;
	}

	/** @brief Deletes a temporary tree created by Pulse. */
	public function DeleteDirectory(string $path): void
	{
		if (!str_starts_with($path, $this->_root . '/storage/backup-') || !is_dir($path))
		{
			return;
		}
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iterator as $entry)
		{
			if ($entry->isDir())
			{
				rmdir($entry->getPathname());
			}
			else
			{
				unlink($entry->getPathname());
			}
		}
		rmdir($path);
	}
}
