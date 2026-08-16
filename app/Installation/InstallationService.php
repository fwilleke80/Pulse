<?php

/**
 * @file InstallationService.php
 * @brief Self-contained installation workflow for new and upgraded Pulse deployments.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Installation;

use DateTimeZone;
use PDO;
use Pulse\Core\ConfigurationValidator;
use Pulse\Core\Database;
use Pulse\Core\Environment;
use Pulse\Core\EnvironmentFile;
use Pulse\Core\MigrationRunner;
use Pulse\Core\UploadMimeTypePolicy;
use RuntimeException;
use Throwable;

/**
 * @brief Coordinates installation state, configuration, migrations, and first-administrator creation.
 */
final class InstallationService
{
	private const STATE_FILENAME = 'install-state.json';
	private const PASSWORD_MINIMUM_LENGTH = 12;

	private string $_rootPath;
	private string $_storagePath;
	private string $_statePath;
	private string $_installerPath;
	private EnvironmentFile $_environmentFile;

	/**
	 * @brief Constructs the installation service.
	 * @param string $rootPath Pulse project root.
	 * @param string $installerPath Absolute path to public/install.php.
	 */
	public function __construct(string $rootPath, string $installerPath)
	{
		$this->_rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
		$this->_storagePath = $this->_rootPath . '/storage';
		$this->_statePath = $this->_storagePath . '/' . self::STATE_FILENAME;
		$this->_installerPath = $installerPath;
		$this->_environmentFile = new EnvironmentFile($this->_rootPath . '/.env');
	}

	/** @brief Returns whether an installation is currently marked as incomplete. */
	public function IsInProgress(): bool
	{
		return is_file($this->_statePath);
	}

	/** @brief Starts a fresh resumable installation without storing secrets in the state marker. */
	public function Begin(): void
	{
		if ($this->IsInProgress())
		{
			return;
		}

		$this->WriteState([
			'started_at' => gmdate('c'),
			'system_checked' => false,
			'database_ready' => false,
			'application_ready' => false,
			'migrations_ready' => false,
			'administrator_ready' => false,
			'mail_ready' => false,
		]);
	}

	/** @brief Removes the temporary installation state marker. */
	public function ClearState(): void
	{
		if (is_file($this->_statePath) && !@unlink($this->_statePath))
		{
			throw new RuntimeException('Unable to remove the installation state marker.');
		}
	}

	/**
	 * @brief Reads the current non-secret installation state.
	 * @return array<string, mixed>
	 */
	public function State(): array
	{
		if (!is_file($this->_statePath) || !is_readable($this->_statePath))
		{
			return [];
		}

		$content = file_get_contents($this->_statePath);
		$decoded = is_string($content) ? json_decode($content, true) : null;
		return is_array($decoded) ? $decoded : [];
	}

	/** @brief Marks one wizard stage complete. @param string $stage State property. */
	public function MarkComplete(string $stage): void
	{
		$allowed = [
			'system_checked',
			'database_ready',
			'application_ready',
			'migrations_ready',
			'administrator_ready',
			'mail_ready',
		];

		if (!in_array($stage, $allowed, true))
		{
			throw new RuntimeException('Invalid installation stage.');
		}

		$state = $this->State();
		$state[$stage] = true;
		$state[$stage . '_at'] = gmdate('c');
		$this->WriteState($state);
	}

	/** @brief Returns the first unfinished wizard step. */
	public function SuggestedStep(): string
	{
		$state = $this->State();

		if (!(bool)($state['system_checked'] ?? false))
		{
			return 'system';
		}

		if (!(bool)($state['database_ready'] ?? false))
		{
			return 'database';
		}

		if (!(bool)($state['application_ready'] ?? false) || !(bool)($state['migrations_ready'] ?? false))
		{
			return 'application';
		}

		if (!(bool)($state['administrator_ready'] ?? false))
		{
			return 'administrator';
		}

		if (!(bool)($state['mail_ready'] ?? false))
		{
			return 'mail';
		}

		return 'finish';
	}

	/**
	 * @brief Performs platform and filesystem readiness checks.
	 * @return array<int, array{key:string,label:string,status:string,blocking:bool,detail:string}>
	 */
	public function SystemChecks(): array
	{
		$checks = [
			$this->Check('php', 'PHP', version_compare(PHP_VERSION, '8.4.0', '>='), true, PHP_VERSION . ' (8.4 or newer required)'),
			$this->Check('pdo', 'PDO extension', extension_loaded('pdo'), true, extension_loaded('pdo') ? 'Available' : 'Missing'),
			$this->Check('pdo_mysql', 'PDO MySQL extension', extension_loaded('pdo_mysql'), true, extension_loaded('pdo_mysql') ? 'Available' : 'Missing'),
			$this->Check('fileinfo', 'Fileinfo extension', extension_loaded('fileinfo'), true, extension_loaded('fileinfo') ? 'Available' : 'Missing'),
			$this->Check('json', 'JSON extension', extension_loaded('json'), true, extension_loaded('json') ? 'Available' : 'Missing'),
			$this->Check('openssl', 'OpenSSL extension', extension_loaded('openssl'), true, extension_loaded('openssl') ? 'Available' : 'Required for secure mail delivery'),
			$this->Check('env', '.env configuration', $this->_environmentFile->IsWritable(), true, $this->_environmentFile->IsWritable() ? 'Writable' : 'Pulse cannot create or update .env'),
			$this->Check('storage', 'Storage directory', is_dir($this->_storagePath) && is_writable($this->_storagePath), true, is_dir($this->_storagePath) && is_writable($this->_storagePath) ? 'Writable' : 'storage/ must be writable'),
		];

		foreach (['logs', 'uploads', 'tmp'] as $directory)
		{
			$path = $this->_storagePath . '/' . $directory;
			$checks[] = $this->Check(
				'storage_' . $directory,
				'storage/' . $directory,
				is_dir($path) && is_writable($path),
				true,
				is_dir($path) && is_writable($path) ? 'Writable' : 'Directory must exist and be writable'
			);
		}

		$migrationPath = $this->_rootPath . '/database/migrations';
		$checks[] = $this->Check('migrations', 'Database migrations', is_dir($migrationPath) && is_readable($migrationPath), true, is_dir($migrationPath) && is_readable($migrationPath) ? 'Available' : 'database/migrations cannot be read');
		$checks[] = $this->Check('self_delete', 'Automatic installer removal', is_writable(dirname($this->_installerPath)), false, is_writable(dirname($this->_installerPath)) ? 'Available' : 'You will need to delete public/install.php manually after installation');
		return $checks;
	}

	/**
	 * @brief Returns whether any platform check blocks installation.
	 * @param array<int, array{key:string,label:string,status:string,blocking:bool,detail:string}> $checks Checks.
	 */
	public function HasBlockingSystemFailure(array $checks): bool
	{
		foreach ($checks as $check)
		{
			if ($check['blocking'] && $check['status'] !== 'ok')
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @brief Detects a complete pre-existing installation without mutating it.
	 * @return array{configured:bool,installed:bool,error:string,base_url:string}
	 */
	public function DetectExistingInstallation(): array
	{
		$values = $this->EffectiveValues();
		$database = trim((string)($values['PULSE_DB_DATABASE'] ?? ''));
		$username = trim((string)($values['PULSE_DB_USERNAME'] ?? ''));
		$baseUrl = trim((string)($values['PULSE_BASE_URL'] ?? ''));

		if ($database === '' || $username === '')
		{
			return ['configured' => false, 'installed' => false, 'error' => '', 'base_url' => $baseUrl];
		}

		try
		{
			$connection = $this->CreateDatabase($values)->GetConnection();

			if (!$this->TableExists($connection, 'users'))
			{
				return ['configured' => true, 'installed' => false, 'error' => '', 'base_url' => $baseUrl];
			}

			$count = (int)$connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
			return ['configured' => true, 'installed' => $count > 0, 'error' => '', 'base_url' => $baseUrl];
		}
		catch (Throwable)
		{
			return [
				'configured' => true,
				'installed' => false,
				'error' => 'Pulse configuration already exists, but the configured database could not be verified. The installer will not overwrite it.',
				'base_url' => $baseUrl,
			];
		}
	}

	/** @brief Tests database credentials without modifying the database. @param array<string, string> $values Values. */
	public function TestDatabase(array $values): void
	{
		$host = trim((string)($values['PULSE_DB_HOST'] ?? ''));
		$port = $this->ValidateInteger((string)($values['PULSE_DB_PORT'] ?? ''), 1, 65535, 'Database port');
		$database = trim((string)($values['PULSE_DB_DATABASE'] ?? ''));
		$username = trim((string)($values['PULSE_DB_USERNAME'] ?? ''));

		if ($host === '' || $database === '' || $username === '')
		{
			throw new RuntimeException('Database host, database name, and username are required.');
		}

		$this->CreateDatabase([
			'PULSE_DB_HOST' => $host,
			'PULSE_DB_PORT' => (string)$port,
			'PULSE_DB_DATABASE' => $database,
			'PULSE_DB_USERNAME' => $username,
			'PULSE_DB_PASSWORD' => (string)($values['PULSE_DB_PASSWORD'] ?? ''),
		])->GetConnection()->query('SELECT 1')->fetchColumn();
	}

	/** @brief Saves verified database credentials into .env. @param array<string, string> $values Values. */
	public function SaveDatabase(array $values): void
	{
		$this->RequireStage('system_checked');
		$existingPassword = $this->FileValue('PULSE_DB_PASSWORD');
		$postedPassword = (string)($values['PULSE_DB_PASSWORD'] ?? '');
		$effectivePassword = $postedPassword !== '' ? $postedPassword : $existingPassword;
		$testValues = $values;
		$testValues['PULSE_DB_PASSWORD'] = $effectivePassword;
		$this->TestDatabase($testValues);
		$this->EnsureEnvironmentTemplate();
		$this->_environmentFile->Update([
			'PULSE_DB_HOST' => trim((string)$values['PULSE_DB_HOST']),
			'PULSE_DB_PORT' => trim((string)$values['PULSE_DB_PORT']),
			'PULSE_DB_DATABASE' => trim((string)$values['PULSE_DB_DATABASE']),
			'PULSE_DB_USERNAME' => trim((string)$values['PULSE_DB_USERNAME']),
			'PULSE_DB_PASSWORD' => $effectivePassword,
		]);
		$this->ResetStages(['application_ready', 'migrations_ready', 'administrator_ready', 'mail_ready']);
		$this->MarkComplete('database_ready');
	}

	/**
	 * @brief Saves first-boot application settings and safe defaults.
	 * @param string $baseUrl Public origin.
	 * @param string $timezone IANA timezone.
	 * @param string $locale Default language.
	 * @param array<int, string> $availableLocales Installed languages.
	 */
	public function SaveApplicationSettings(string $baseUrl, string $timezone, string $locale, array $availableLocales): void
	{
		$this->RequireStage('database_ready');
		$baseUrl = rtrim(trim($baseUrl), '/');
		$parts = parse_url($baseUrl);

		if (
			$parts === false
			|| !isset($parts['scheme'], $parts['host'])
			|| isset($parts['user'])
			|| isset($parts['pass'])
			|| isset($parts['query'])
			|| isset($parts['fragment'])
			|| (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')
		)
		{
			throw new RuntimeException('The public base URL must be a complete site origin without a path, query, credentials, or fragment.');
		}

		$scheme = strtolower((string)$parts['scheme']);

		if (!in_array($scheme, ['http', 'https'], true))
		{
			throw new RuntimeException('The public base URL must use HTTP or HTTPS.');
		}

		try
		{
			new DateTimeZone($timezone);
		}
		catch (Throwable $throwable)
		{
			throw new RuntimeException('The selected timezone is invalid.', 0, $throwable);
		}

		if (!in_array($locale, $availableLocales, true))
		{
			throw new RuntimeException('The selected default language is not installed.');
		}

		$isSecure = $scheme === 'https';

		if ($isSecure && trim((string)($this->EffectiveValues()['PULSE_DB_PASSWORD'] ?? '')) === '')
		{
			throw new RuntimeException('A production installation requires a database password.');
		}

		$existing = $this->_environmentFile->ReadValues();
		$cronToken = trim((string)($existing['PULSE_CRON_TOKEN'] ?? ''));
		$totpEncryptionKey = trim((string)($existing['PULSE_TOTP_ENCRYPTION_KEY'] ?? ''));

		if (strlen($cronToken) < 32)
		{
			$cronToken = bin2hex(random_bytes(32));
		}

		$decodedTotpKey = base64_decode($totpEncryptionKey, true);

		if (!is_string($decodedTotpKey) || strlen($decodedTotpKey) !== 32)
		{
			$totpEncryptionKey = base64_encode(random_bytes(32));
		}

		$this->_environmentFile->Update([
			'PULSE_ENV' => $isSecure ? 'production' : 'development',
			'PULSE_DEBUG' => 'false',
			'PULSE_BASE_URL' => $baseUrl,
			'PULSE_DISPLAY_TIMEZONE' => $timezone,
			'PULSE_DEFAULT_LOCALE' => $locale,
			'PULSE_TRUSTED_HOSTS' => strtolower((string)$parts['host']),
			'PULSE_SESSION_NAME' => 'pulse_session',
			'PULSE_COOKIE_SECURE' => $isSecure ? 'true' : 'false',
			'PULSE_COOKIE_SAMESITE' => 'Strict',
			'PULSE_HSTS_ENABLED' => $isSecure ? 'true' : 'false',
			'PULSE_SESSION_IDLE_TIMEOUT' => '1800',
			'PULSE_SESSION_ABSOLUTE_TIMEOUT' => '43200',
			'PULSE_SESSION_REGENERATION_INTERVAL' => '900',
			'PULSE_LOGIN_MAX_ATTEMPTS' => '5',
			'PULSE_LOGIN_WINDOW_SECONDS' => '900',
			'PULSE_LOGIN_BLOCK_SECONDS' => '900',
			'PULSE_PASSWORD_MINIMUM_LENGTH' => (string)self::PASSWORD_MINIMUM_LENGTH,
			'PULSE_PASSKEY_QUICK_CHECKIN_ENABLED' => 'false',
			'PULSE_TOTP_ENCRYPTION_KEY' => $totpEncryptionKey,
			'PULSE_TOTP_MAX_ATTEMPTS' => '6',
			'PULSE_TOTP_WINDOW_SECONDS' => '300',
			'PULSE_TOTP_BLOCK_SECONDS' => '300',
			'PULSE_UPLOAD_MAXIMUM_BYTES' => '26214400',
			'PULSE_UPLOAD_ALLOWED_MIME_TYPES' => UploadMimeTypePolicy::DefaultsCsv(),
			'PULSE_MAIL_ENABLED' => 'false',
			'PULSE_SMTP_HOST' => '',
			'PULSE_SMTP_PORT' => '587',
			'PULSE_SMTP_ENCRYPTION' => 'starttls',
			'PULSE_SMTP_USERNAME' => '',
			'PULSE_SMTP_PASSWORD' => '',
			'PULSE_MAIL_FROM_ADDRESS' => '',
			'PULSE_MAIL_FROM_NAME' => 'Pulse',
			'PULSE_SMTP_TIMEOUT_SECONDS' => '15',
			'PULSE_MAIL_MAX_ATTEMPTS' => '5',
			'PULSE_MAIL_RETRY_DELAYS_SECONDS' => '60,300,1800,7200',
			'PULSE_MAIL_LEASE_SECONDS' => '120',
			'PULSE_MAIL_WORKER_BATCH_SIZE' => '25',
			'PULSE_CRON_TOKEN' => $cronToken,
		]);
		$this->ResetStages(['migrations_ready', 'administrator_ready', 'mail_ready']);
		$this->MarkComplete('application_ready');
	}

	/** @brief Applies pending schema migrations. @return array<int, string> */
	public function RunMigrations(): array
	{
		$this->RequireStage('application_ready');
		$runner = new MigrationRunner($this->CreateDatabase($this->EffectiveValues()), $this->_rootPath . '/database/migrations');
		$result = $runner->Migrate();
		$this->MarkComplete('migrations_ready');
		return $result;
	}

	/**
	 * @brief Creates or recognizes the first active administrator.
	 * @param string $displayName Display name.
	 * @param string $email Login email.
	 * @param string $password Password.
	 * @param string $locale Initial locale.
	 */
	public function CreateAdministrator(string $displayName, string $email, string $password, string $locale): void
	{
		$this->RequireStage('migrations_ready');
		$displayName = trim($displayName);
		$email = strtolower(trim($email));

		if ($displayName === '')
		{
			throw new RuntimeException('Administrator name is required.');
		}

		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
		{
			throw new RuntimeException('Enter a valid administrator email address.');
		}

		if (strlen($password) < self::PASSWORD_MINIMUM_LENGTH)
		{
			throw new RuntimeException('The administrator password must contain at least ' . self::PASSWORD_MINIMUM_LENGTH . ' characters.');
		}

		$connection = $this->CreateDatabase($this->EffectiveValues())->GetConnection();

		if (!$this->TableExists($connection, 'users'))
		{
			throw new RuntimeException('The Pulse database schema has not been initialized.');
		}

		$count = (int)$connection->query('SELECT COUNT(*) FROM users')->fetchColumn();

		if ($count > 0)
		{
			$administratorCount = (int)$connection->query("SELECT COUNT(*) FROM users WHERE role = 'administrator' AND is_active = 1")->fetchColumn();

			if ($administratorCount > 0)
			{
				$this->MarkComplete('administrator_ready');
				return;
			}

			throw new RuntimeException('A user account already exists, but no active administrator was found. The installer will not alter existing accounts.');
		}

		$passwordHash = password_hash($password, PASSWORD_DEFAULT);

		if (!is_string($passwordHash) || $passwordHash === '')
		{
			throw new RuntimeException('Unable to create the administrator password hash.');
		}

		$statement = $connection->prepare('
			INSERT INTO users
			(email, password_hash, display_name, role, notification_locale, website_locale, is_active)
			VALUES (:email, :password_hash, :display_name, \'administrator\', :notification_locale, :website_locale, 1)
		');
		$statement->execute([
			'email' => $email,
			'password_hash' => $passwordHash,
			'display_name' => $displayName,
			'notification_locale' => $locale,
			'website_locale' => $locale,
		]);
		$this->ResetStages(['mail_ready']);
		$this->MarkComplete('administrator_ready');
	}

	/** @brief Saves optional SMTP configuration and enables mail. @param array<string, string> $values Values. */
	public function SaveMail(array $values): void
	{
		$this->RequireStage('administrator_ready');
		$host = trim((string)($values['PULSE_SMTP_HOST'] ?? ''));
		$port = $this->ValidateInteger((string)($values['PULSE_SMTP_PORT'] ?? ''), 1, 65535, 'SMTP port');
		$encryption = strtolower(trim((string)($values['PULSE_SMTP_ENCRYPTION'] ?? 'starttls')));
		$username = trim((string)($values['PULSE_SMTP_USERNAME'] ?? ''));
		$postedPassword = (string)($values['PULSE_SMTP_PASSWORD'] ?? '');
		$existingUsername = trim($this->FileValue('PULSE_SMTP_USERNAME'));
		$existingPassword = $this->FileValue('PULSE_SMTP_PASSWORD');
		$password = $postedPassword;

		if ($postedPassword === '' && $username !== '' && $username === $existingUsername)
		{
			$password = $existingPassword;
		}

		$fromAddress = strtolower(trim((string)($values['PULSE_MAIL_FROM_ADDRESS'] ?? '')));
		$fromName = trim((string)($values['PULSE_MAIL_FROM_NAME'] ?? 'Pulse'));

		if ($host === '')
		{
			throw new RuntimeException('SMTP host is required.');
		}

		if (filter_var($host, FILTER_VALIDATE_IP) === false && preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $host) !== 1)
		{
			throw new RuntimeException('SMTP host must be a valid hostname or IP address.');
		}

		if (!in_array($encryption, ['starttls', 'tls', 'none'], true))
		{
			throw new RuntimeException('Choose STARTTLS, TLS, or no SMTP encryption.');
		}

		$environment = strtolower((string)($this->EffectiveValues()['PULSE_ENV'] ?? 'production'));

		if ($environment === 'production' && $encryption === 'none')
		{
			throw new RuntimeException('Production installations require TLS or STARTTLS for SMTP.');
		}

		if (($username === '') !== ($password === ''))
		{
			throw new RuntimeException('SMTP username and password must either both be supplied or both be left empty.');
		}

		if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false)
		{
			throw new RuntimeException('Enter a valid sender email address.');
		}

		if ($fromName === '' || strpbrk($fromName, "\r\n") !== false)
		{
			throw new RuntimeException('Enter a valid sender name.');
		}

		$this->_environmentFile->Update([
			'PULSE_MAIL_ENABLED' => 'true',
			'PULSE_SMTP_HOST' => $host,
			'PULSE_SMTP_PORT' => (string)$port,
			'PULSE_SMTP_ENCRYPTION' => $encryption,
			'PULSE_SMTP_USERNAME' => $username,
			'PULSE_SMTP_PASSWORD' => $password,
			'PULSE_MAIL_FROM_ADDRESS' => $fromAddress,
			'PULSE_MAIL_FROM_NAME' => $fromName,
		]);
		$this->MarkComplete('mail_ready');
	}

	/** @brief Leaves mail disabled and completes the optional mail stage. */
	public function SkipMail(): void
	{
		$this->RequireStage('administrator_ready');
		$this->_environmentFile->Update(['PULSE_MAIL_ENABLED' => 'false']);
		$this->MarkComplete('mail_ready');
	}

	/**
	 * @brief Verifies exactly the configuration normal Pulse bootstrap will consume.
	 * @return array{base_url:string,cron_token:string,mail_enabled:bool,administrator_email:string}
	 */
	public function VerifyInstallation(): array
	{
		Environment::Load($this->_environmentFile->Path());
		$appConfig = require $this->_rootPath . '/config/app.php';
		$databaseConfig = require $this->_rootPath . '/config/database.php';
		ConfigurationValidator::Validate($appConfig, $databaseConfig);
		$values = $this->EffectiveValues();

		foreach (['PULSE_BASE_URL', 'PULSE_DISPLAY_TIMEZONE', 'PULSE_DEFAULT_LOCALE', 'PULSE_CRON_TOKEN'] as $name)
		{
			if (trim((string)($values[$name] ?? '')) === '')
			{
				throw new RuntimeException('Installation verification failed because required configuration is incomplete.');
			}
		}

		$connection = $this->CreateDatabase($values)->GetConnection();

		foreach (['schema_migrations', 'users'] as $table)
		{
			if (!$this->TableExists($connection, $table))
			{
				throw new RuntimeException('Installation verification failed because the database schema is incomplete.');
			}
		}

		$email = $connection->query("SELECT email FROM users WHERE role = 'administrator' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();

		if (!is_string($email) || $email === '')
		{
			throw new RuntimeException('Installation verification failed because no active administrator exists.');
		}

		$state = $this->State();

		foreach (['system_checked', 'database_ready', 'application_ready', 'migrations_ready', 'administrator_ready', 'mail_ready'] as $stage)
		{
			if (!(bool)($state[$stage] ?? false))
			{
				throw new RuntimeException('Installation verification failed because the wizard is incomplete.');
			}
		}

		return [
			'base_url' => rtrim((string)$values['PULSE_BASE_URL'], '/'),
			'cron_token' => (string)$values['PULSE_CRON_TOKEN'],
			'mail_enabled' => strtolower((string)($values['PULSE_MAIL_ENABLED'] ?? 'false')) === 'true',
			'administrator_email' => $email,
		];
	}

	/** @brief Attempts to delete public/install.php. */
	public function RemoveInstaller(): bool
	{
		if (!is_file($this->_installerPath))
		{
			return true;
		}

		return @unlink($this->_installerPath) || !is_file($this->_installerPath);
	}

	/** @brief Returns a value defined specifically in .env. */
	public function FileValue(string $name, string $fallback = ''): string
	{
		$values = $this->_environmentFile->ReadValues();
		return array_key_exists($name, $values) ? (string)$values[$name] : $fallback;
	}

	/** @brief Returns .env values with process-environment overrides applied. @return array<string, string> */
	public function EffectiveValues(): array
	{
		$values = $this->_environmentFile->ReadValues();
		$knownKeys = [
			'PULSE_ENV', 'PULSE_DEBUG', 'PULSE_BASE_URL', 'PULSE_DISPLAY_TIMEZONE', 'PULSE_DEFAULT_LOCALE', 'PULSE_TRUSTED_HOSTS',
			'PULSE_DB_HOST', 'PULSE_DB_PORT', 'PULSE_DB_DATABASE', 'PULSE_DB_USERNAME', 'PULSE_DB_PASSWORD',
			'PULSE_SESSION_NAME', 'PULSE_COOKIE_SECURE', 'PULSE_COOKIE_SAMESITE', 'PULSE_HSTS_ENABLED', 'PULSE_SESSION_IDLE_TIMEOUT', 'PULSE_SESSION_ABSOLUTE_TIMEOUT', 'PULSE_SESSION_REGENERATION_INTERVAL',
			'PULSE_LOGIN_MAX_ATTEMPTS', 'PULSE_LOGIN_WINDOW_SECONDS', 'PULSE_LOGIN_BLOCK_SECONDS', 'PULSE_PASSWORD_MINIMUM_LENGTH', 'PULSE_PASSKEY_QUICK_CHECKIN_ENABLED',
			'PULSE_TOTP_ENCRYPTION_KEY', 'PULSE_TOTP_MAX_ATTEMPTS', 'PULSE_TOTP_WINDOW_SECONDS', 'PULSE_TOTP_BLOCK_SECONDS',
			'PULSE_UPLOAD_MAXIMUM_BYTES', 'PULSE_UPLOAD_ALLOWED_MIME_TYPES',
			'PULSE_MAIL_ENABLED', 'PULSE_SMTP_HOST', 'PULSE_SMTP_PORT', 'PULSE_SMTP_ENCRYPTION', 'PULSE_SMTP_USERNAME', 'PULSE_SMTP_PASSWORD', 'PULSE_MAIL_FROM_ADDRESS', 'PULSE_MAIL_FROM_NAME', 'PULSE_SMTP_TIMEOUT_SECONDS', 'PULSE_MAIL_MAX_ATTEMPTS', 'PULSE_MAIL_RETRY_DELAYS_SECONDS', 'PULSE_MAIL_LEASE_SECONDS', 'PULSE_MAIL_WORKER_BATCH_SIZE',
			'PULSE_CRON_TOKEN',
		];

		foreach ($knownKeys as $name)
		{
			$processValue = getenv($name);

			if ($processValue !== false)
			{
				$values[$name] = (string)$processValue;
			}
		}

		return $values;
	}

	/** @brief Requires a previously completed installation stage before mutating dependent state. */
	private function RequireStage(string $stage): void
	{
		$state = $this->State();

		if (!(bool)($state[$stage] ?? false))
		{
			throw new RuntimeException('Installation steps must be completed in order.');
		}
	}

	/** @brief Resets dependent stages after an earlier setting changes. @param array<int, string> $stages Stages. */
	private function ResetStages(array $stages): void
	{
		$state = $this->State();

		foreach ($stages as $stage)
		{
			$state[$stage] = false;
			unset($state[$stage . '_at']);
		}

		$this->WriteState($state);
	}

	/** @brief Copies the reference dotenv template for a new installation when possible. */
	private function EnsureEnvironmentTemplate(): void
	{
		if (is_file($this->_environmentFile->Path()))
		{
			return;
		}

		$templatePath = $this->_rootPath . '/.env.example';

		if (!is_file($templatePath) || !is_readable($templatePath))
		{
			return;
		}

		$content = file_get_contents($templatePath);

		if (!is_string($content) || file_put_contents($this->_environmentFile->Path(), $content, LOCK_EX) === false)
		{
			throw new RuntimeException('Unable to create .env from .env.example.');
		}

		@chmod($this->_environmentFile->Path(), 0600);
	}

	/** @brief Writes the non-secret installation state. @param array<string, mixed> $state State. */
	private function WriteState(array $state): void
	{
		if (!is_dir($this->_storagePath) || !is_writable($this->_storagePath))
		{
			throw new RuntimeException('Pulse storage is not writable.');
		}

		$content = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

		if (!is_string($content) || file_put_contents($this->_statePath, $content . "\n", LOCK_EX) === false)
		{
			throw new RuntimeException('Unable to save installation state.');
		}

		@chmod($this->_statePath, 0600);
	}

	/** @brief Creates a database wrapper from installer/environment values. @param array<string, string> $values Values. */
	private function CreateDatabase(array $values): Database
	{
		return new Database([
			'host' => (string)($values['PULSE_DB_HOST'] ?? 'localhost'),
			'port' => (int)($values['PULSE_DB_PORT'] ?? 3306),
			'database' => (string)($values['PULSE_DB_DATABASE'] ?? ''),
			'username' => (string)($values['PULSE_DB_USERNAME'] ?? ''),
			'password' => (string)($values['PULSE_DB_PASSWORD'] ?? ''),
			'charset' => 'utf8mb4',
		]);
	}

	/** @brief Returns whether a table exists. */
	private function TableExists(PDO $connection, string $table): bool
	{
		$statement = $connection->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name');
		$statement->execute(['table_name' => $table]);
		return (int)$statement->fetchColumn() > 0;
	}

	/** @brief Validates an integer form value. */
	private function ValidateInteger(string $value, int $minimum, int $maximum, string $label): int
	{
		if (preg_match('/^\d+$/', trim($value)) !== 1)
		{
			throw new RuntimeException($label . ' must be a whole number.');
		}

		$integer = (int)$value;

		if ($integer < $minimum || $integer > $maximum)
		{
			throw new RuntimeException($label . ' must be between ' . $minimum . ' and ' . $maximum . '.');
		}

		return $integer;
	}

	/** @brief Builds a system check result. */
	private function Check(string $key, string $label, bool $success, bool $blocking, string $detail): array
	{
		return [
			'key' => $key,
			'label' => $label,
			'status' => $success ? 'ok' : ($blocking ? 'error' : 'warning'),
			'blocking' => $blocking,
			'detail' => $detail,
		];
	}
}
