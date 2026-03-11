<?php

declare(strict_types=1);

namespace Pulse\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * @brief PDO database wrapper for Pulse.
 */
class Database
{
	private array $_config;
	private ?PDO $_pdo;

	/**
	 * @brief Constructs the database service.
	 * @param array<string, mixed> $config Database configuration values.
	 */
	public function __construct(array $config)
	{
		$this->_config = $config;
		$this->_pdo = null;
	}

	/**
	 * @brief Returns the PDO connection.
	 * @return PDO Active PDO connection.
	 */
	public function GetConnection(): PDO
	{
		if ($this->_pdo instanceof PDO)
		{
			return $this->_pdo;
		}

		$host = (string)$this->_config['host'];
		$port = (int)$this->_config['port'];
		$database = (string)$this->_config['database'];
		$charset = (string)$this->_config['charset'];
		$username = (string)$this->_config['username'];
		$password = (string)$this->_config['password'];

		$dsn = sprintf(
			'mysql:host=%s;port=%d;dbname=%s;charset=%s',
			$host,
			$port,
			$database,
			$charset
		);

		try
		{
			$this->_pdo = new PDO(
				$dsn,
				$username,
				$password,
				[
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
					PDO::ATTR_EMULATE_PREPARES => false,
				]
			);
		}
		catch (PDOException $exception)
		{
			throw new RuntimeException('Database connection failed: ' . $exception->getMessage(), 0, $exception);
		}

		return $this->_pdo;
	}

	/**
	 * @brief Tests whether the database connection can be established.
	 * @return bool True if the connection succeeds, otherwise false.
	 */
	public function CanConnect(): bool
	{
		try
		{
			$this->GetConnection();
			return true;
		}
		catch (RuntimeException)
		{
			return false;
		}
	}
}