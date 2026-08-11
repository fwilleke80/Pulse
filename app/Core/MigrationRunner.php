<?php

/**
 * @file MigrationRunner.php
 * @brief Ordered, checksummed SQL migration runner for Pulse.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

use PDO;
use RuntimeException;
use Throwable;

/**
 * @brief Applies pending SQL migrations and baselines pre-0.3.0 installations.
 */
class MigrationRunner
{
	private const LOCK_TIMEOUT_SECONDS = 15;

	private Database $_database;
	private string $_migrationDirectory;

	/** @brief Constructs the runner. @param Database $database Database service. @param string $migrationDirectory Migration directory. */
	public function __construct(Database $database, string $migrationDirectory)
	{
		$this->_database = $database;
		$this->_migrationDirectory = rtrim($migrationDirectory, '/');
	}

	/**
	 * @brief Applies all pending migrations.
	 * @return array<int, string> Applied migration filenames.
	 */
	public function Migrate(): array
	{
		$connection = $this->_database->GetConnection();
		$files = $this->MigrationFiles();

		if (!$this->RequiresMigration($connection, $files))
		{
			return [];
		}

		$lockName = $this->LockName($connection);
		$this->AcquireLock($connection, $lockName);

		try
		{
			$this->EnsureMigrationTable($connection);
			$this->BaselineLegacyInstallation($connection, $files);
			$applied = $this->AppliedMigrations($connection);
			$result = [];

			foreach ($files as $path)
			{
				$name = basename($path);
				$sql = $this->ReadMigration($path);
				$checksum = hash('sha256', $sql);

				if (isset($applied[$name]))
				{
					$this->ValidateChecksum($name, $checksum, $applied[$name]);
					continue;
				}

				foreach (self::SplitStatements($sql) as $statement)
				{
					$connection->exec($statement);
				}

				$record = $connection->prepare('
					INSERT INTO schema_migrations (migration, checksum, applied_at)
					VALUES (:migration, :checksum, UTC_TIMESTAMP())
				');
				$record->execute(['migration' => $name, 'checksum' => $checksum]);
				$result[] = $name;
			}

			return $result;
		}
		finally
		{
			$this->ReleaseLock($connection, $lockName);
		}
	}

	/**
	 * @brief Splits a SQL script on semicolons outside strings, identifiers, and comments.
	 * @param string $sql SQL script.
	 * @return array<int, string>
	 */
	public static function SplitStatements(string $sql): array
	{
		$statements = [];
		$current = '';
		$quote = null;
		$inLineComment = false;
		$inBlockComment = false;
		$length = strlen($sql);

		for ($index = 0; $index < $length; ++$index)
		{
			$character = $sql[$index];
			$next = $index + 1 < $length ? $sql[$index + 1] : '';

			if ($inLineComment)
			{
				if ($character === "\n")
				{
					$inLineComment = false;
					$current .= $character;
				}
				continue;
			}

			if ($inBlockComment)
			{
				if ($character === '*' && $next === '/')
				{
					$inBlockComment = false;
					++$index;
				}
				continue;
			}

			if ($quote === null && (($character === '-' && $next === '-') || $character === '#'))
			{
				$inLineComment = true;

				if ($character === '-')
				{
					++$index;
				}
				continue;
			}

			if ($quote === null && $character === '/' && $next === '*')
			{
				$inBlockComment = true;
				++$index;
				continue;
			}

			if ($quote !== null)
			{
				$current .= $character;

				if ($character === '\\' && $index + 1 < $length)
				{
					$current .= $sql[++$index];
					continue;
				}

				if ($character === $quote)
				{
					if ($index + 1 < $length && $sql[$index + 1] === $quote)
					{
						$current .= $sql[++$index];
						continue;
					}

					$quote = null;
				}
				continue;
			}

			if (in_array($character, ["'", '"', '`'], true))
			{
				$quote = $character;
				$current .= $character;
				continue;
			}

			if ($character === ';')
			{
				$statement = trim($current);

				if ($statement !== '')
				{
					$statements[] = $statement;
				}

				$current = '';
				continue;
			}

			$current .= $character;
		}

		$statement = trim($current);

		if ($statement !== '')
		{
			$statements[] = $statement;
		}

		return $statements;
	}

	/** @brief Creates the migration-state table. @param PDO $connection PDO connection. */
	private function EnsureMigrationTable(PDO $connection): void
	{
		$connection->exec('
			CREATE TABLE IF NOT EXISTS schema_migrations
			(
				migration VARCHAR(255) NOT NULL PRIMARY KEY,
				checksum CHAR(64) NOT NULL,
				applied_at DATETIME NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		');
	}

	/**
	 * @brief Returns all migration files in their application order.
	 * @return array<int, string> Absolute migration paths.
	 */
	private function MigrationFiles(): array
	{
		$files = glob($this->_migrationDirectory . '/*.sql');

		if (!is_array($files))
		{
			throw new RuntimeException('Unable to enumerate database migrations.');
		}

		sort($files, SORT_STRING);
		return $files;
	}

	/**
	 * @brief Checks whether the database needs work without taking the migration lock.
	 * @param PDO $connection Active database connection.
	 * @param array<int, string> $files Migration files.
	 * @return bool True if one or more migrations must be applied.
	 */
	private function RequiresMigration(PDO $connection, array $files): bool
	{
		if (!$this->TableExists($connection, 'schema_migrations'))
		{
			return true;
		}

		$applied = $this->AppliedMigrations($connection);

		foreach ($files as $path)
		{
			$name = basename($path);

			if (!isset($applied[$name]))
			{
				return true;
			}

			$sql = $this->ReadMigration($path);
			$this->ValidateChecksum($name, hash('sha256', $sql), $applied[$name]);
		}

		return false;
	}

	/** @brief Reads one migration file. @param string $path Migration path. @return string SQL contents. */
	private function ReadMigration(string $path): string
	{
		$sql = file_get_contents($path);

		if (!is_string($sql))
		{
			throw new RuntimeException('Unable to read migration: ' . basename($path));
		}

		return $sql;
	}

	/** @brief Verifies an applied migration checksum. @param string $name Filename. @param string $expected Current checksum. @param string $applied Stored checksum. */
	private function ValidateChecksum(string $name, string $expected, string $applied): void
	{
		if ($applied !== 'legacy-baseline' && !hash_equals($applied, $expected))
		{
			throw new RuntimeException('Applied migration was modified: ' . $name);
		}
	}

	/** @brief Builds a database-specific advisory-lock name. @param PDO $connection Active connection. @return string Lock name. */
	private function LockName(PDO $connection): string
	{
		$databaseName = (string)$connection->query('SELECT DATABASE()')->fetchColumn();
		return 'pulse-migrate-' . substr(hash('sha256', $databaseName), 0, 32);
	}

	/** @brief Acquires the migration advisory lock. @param PDO $connection Active connection. @param string $lockName Lock name. */
	private function AcquireLock(PDO $connection, string $lockName): void
	{
		$statement = $connection->prepare('SELECT GET_LOCK(:lock_name, ' . self::LOCK_TIMEOUT_SECONDS . ')');
		$statement->bindValue('lock_name', $lockName);
		$statement->execute();

		if ((int)$statement->fetchColumn() !== 1)
		{
			throw new RuntimeException('Unable to acquire the database migration lock.');
		}
	}

	/** @brief Releases the migration advisory lock without hiding an earlier migration failure. @param PDO $connection Active connection. @param string $lockName Lock name. */
	private function ReleaseLock(PDO $connection, string $lockName): void
	{
		try
		{
			$statement = $connection->prepare('SELECT RELEASE_LOCK(:lock_name)');
			$statement->execute(['lock_name' => $lockName]);
		}
		catch (Throwable)
		{
			// Advisory locks are also released automatically when the connection closes.
		}
	}

	/** @brief Returns applied migrations indexed by filename. @param PDO $connection PDO connection. @return array<string, string> */
	private function AppliedMigrations(PDO $connection): array
	{
		$rows = $connection->query('SELECT migration, checksum FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_ASSOC);
		$result = [];

		foreach (is_array($rows) ? $rows : [] as $row)
		{
			$result[(string)$row['migration']] = (string)$row['checksum'];
		}

		return $result;
	}

	/**
	 * @brief Marks the consolidated pre-0.3.0 schema as applied when upgrading an existing installation.
	 * @param PDO $connection PDO connection.
	 * @param array<int, string> $files Migration files.
	 */
	private function BaselineLegacyInstallation(PDO $connection, array $files): void
	{
		$count = (int)$connection->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();

		if ($count !== 0 || !$this->TableExists($connection, 'users'))
		{
			return;
		}

		$record = $connection->prepare('
			INSERT INTO schema_migrations (migration, checksum, applied_at)
			VALUES (:migration, :checksum, UTC_TIMESTAMP())
		');

		foreach ($files as $path)
		{
			$name = basename($path);

			if (!str_starts_with($name, '001_') && !str_starts_with($name, '002_'))
			{
				continue;
			}

			$record->execute(['migration' => $name, 'checksum' => 'legacy-baseline']);
		}
	}

	/** @brief Returns whether a table exists in the active database. @param PDO $connection PDO connection. @param string $table Table name. @return bool */
	private function TableExists(PDO $connection, string $table): bool
	{
		$statement = $connection->prepare('
			SELECT 1
			FROM information_schema.tables
			WHERE table_schema = DATABASE()
			  AND table_name = :table_name
			LIMIT 1
		');
		$statement->execute(['table_name' => $table]);

		return $statement->fetchColumn() !== false;
	}
}
