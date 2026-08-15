<?php

/**
 * @file AdministrationController.php
 * @brief Administrator-only configuration and mail operations.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use Pulse\Core\Environment;
use Pulse\Core\EnvironmentFile;
use Pulse\Core\Logger;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Repositories\MailQueueRepository;
use Pulse\Repositories\SystemStatusRepository;
use Pulse\Services\AuthService;
use Pulse\Services\TestNotificationService;
use RuntimeException;

/**
 * @brief Provides the administrator-only configuration surface.
 */
final class AdministrationController extends BaseController
{
	private const TABS = ['general', 'security', 'files', 'mail', 'cron', 'installation'];

	private EnvironmentFile $_environmentFile;
	private MailQueueRepository $_mailQueueRepository;
	private SystemStatusRepository $_systemStatusRepository;
	private TestNotificationService $_testNotificationService;
	private bool $_debugEnabled;
	private bool $_mailEnabled;

	/** @var array<int, string> */
	private array $_availableLocales;

	/** @var array<string, mixed> */
	private array $_databaseConfig;

	/**
	 * @brief Constructs the administration controller.
	 * @param View $view View renderer.
	 * @param Session $session Session service.
	 * @param AuthService $auth Authentication service.
	 * @param Logger $logger Application logger.
	 * @param Request $request Current request.
	 * @param EnvironmentFile $environmentFile Root .env editor.
	 * @param MailQueueRepository $mailQueueRepository Mail queue repository.
	 * @param SystemStatusRepository $systemStatusRepository Installation runtime-status repository.
	 * @param TestNotificationService $testNotificationService Test-mail service.
	 * @param bool $debugEnabled Effective debug-mode state.
	 * @param bool $mailEnabled Effective mail-delivery state.
	 * @param array<int, string> $availableLocales Available application locales.
	 * @param array<string, mixed> $databaseConfig Effective database configuration.
	 */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		EnvironmentFile $environmentFile,
		MailQueueRepository $mailQueueRepository,
		SystemStatusRepository $systemStatusRepository,
		TestNotificationService $testNotificationService,
		bool $debugEnabled,
		bool $mailEnabled,
		array $availableLocales,
		array $databaseConfig
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_environmentFile = $environmentFile;
		$this->_mailQueueRepository = $mailQueueRepository;
		$this->_systemStatusRepository = $systemStatusRepository;
		$this->_testNotificationService = $testNotificationService;
		$this->_debugEnabled = $debugEnabled;
		$this->_mailEnabled = $mailEnabled;
		$this->_availableLocales = $availableLocales;
		$this->_databaseConfig = $databaseConfig;
	}

	/**
	 * @brief Displays Administration with responsive task-focused tabs.
	 * @return string
	 */
	public function Index(): string
	{
		$user = $this->RequireAdministrator();
		$activeTab = $this->NormalizeTab($this->_request->QueryString('tab', 32));
		$settings = $this->CurrentSettings();
		$availableTimezones = DateTimeZone::listIdentifiers();

		if (!in_array($settings['PULSE_DISPLAY_TIMEZONE'], $availableTimezones, true))
		{
			array_unshift($availableTimezones, $settings['PULSE_DISPLAY_TIMEZONE']);
		}

		$processOverrides = $this->ProcessOverrides(array_keys($settings));
		$lastSuccessfulCronRun = $this->_systemStatusRepository->LastSuccessfulCronRun();
		$cronStatus = $this->CronStatus($lastSuccessfulCronRun);
		$issues = $this->ConfigurationIssues($settings, $processOverrides, $cronStatus);

		return $this->_view->Render('administration.index', [
			'user' => $user,
			'activeTab' => $activeTab,
			'settings' => $settings,
			'availableLocales' => $this->_availableLocales,
			'availableTimezones' => $availableTimezones,
			'environmentWritable' => $this->_environmentFile->IsWritable(),
			'environmentExists' => is_file($this->_environmentFile->Path()),
			'processOverrides' => $processOverrides,
			'configurationIssues' => $issues,
			'mailEnabled' => $this->_mailEnabled,
			'mailQueueCounts' => $this->_mailQueueRepository->CountByStatus(),
			'mailQueueEntries' => $this->_mailQueueRepository->FindRecent(50),
			'latestTestNotification' => $this->_mailQueueRepository->FindLatestTestForUser((int)$user['id']),
			'debugEnabled' => $this->_debugEnabled,
			'databaseConfig' => $this->_databaseConfig,
			'lastSuccessfulCronRun' => $lastSuccessfulCronRun,
			'cronStatus' => $cronStatus,
		]);
	}

	/**
	 * @brief Validates and atomically saves editable application settings to .env.
	 */
	public function Update(): void
	{
		$user = $this->RequireAdministrator();
		$activeTab = $this->NormalizeTab($this->_request->PostString('active_tab', 32));
		$values = $this->PostedSettings();
		$fileValues = $this->_environmentFile->ReadValues();

		$smtpPassword = $this->_request->PostString('PULSE_SMTP_PASSWORD', 4096, false);
		$clearSmtpPassword = $this->_request->PostBool('clear_smtp_password');

		if ($clearSmtpPassword)
		{
			$values['PULSE_SMTP_PASSWORD'] = '';
		}
		else if ($smtpPassword !== '')
		{
			$values['PULSE_SMTP_PASSWORD'] = $smtpPassword;
		}

		$cronToken = $this->_request->PostString('PULSE_CRON_TOKEN', 4096, false);
		$clearCronToken = $this->_request->PostBool('clear_cron_token');
		if ($clearCronToken)
		{
			$values['PULSE_CRON_TOKEN'] = '';
		}
		else if ($cronToken !== '')
		{
			$values['PULSE_CRON_TOKEN'] = $cronToken;
		}

		$fileSmtpPassword = array_key_exists('PULSE_SMTP_PASSWORD', $values)
			? (string)$values['PULSE_SMTP_PASSWORD']
			: (string)($fileValues['PULSE_SMTP_PASSWORD'] ?? '');
		$fileCronToken = array_key_exists('PULSE_CRON_TOKEN', $values)
			? (string)$values['PULSE_CRON_TOKEN']
			: (string)($fileValues['PULSE_CRON_TOKEN'] ?? '');

		try
		{
			// Validate both the persisted .env configuration and the effective runtime
			// configuration when the web-server process overrides selected keys.
			$validationValues = $values;
			$validationValues['PULSE_BASE_URL'] = Environment::Get('PULSE_BASE_URL');
			$this->ValidateSettings($validationValues, $fileSmtpPassword, $fileCronToken);

			$runtimeValues = $validationValues;

			foreach (array_keys($runtimeValues) as $key)
			{
				$override = getenv($key);

				if (is_string($override))
				{
					$runtimeValues[$key] = $override;
				}
			}

			$runtimeSmtpPassword = getenv('PULSE_SMTP_PASSWORD');
			$runtimeCronToken = getenv('PULSE_CRON_TOKEN');
			$this->ValidateSettings(
				$runtimeValues,
				is_string($runtimeSmtpPassword) ? $runtimeSmtpPassword : $fileSmtpPassword,
				is_string($runtimeCronToken) ? $runtimeCronToken : $fileCronToken
			);
			$this->_environmentFile->Update($values);
		}
		catch (RuntimeException $exception)
		{
			$this->_logger->Warning('Administration settings update failed', [
				'user_id' => (int)$user['id'],
				'error' => $exception->getMessage(),
			]);
			$this->Flash('error', __('administration.settings.invalid', ['message' => $exception->getMessage()]));
			$this->Redirect('/administration?tab=' . rawurlencode($activeTab));
		}

		$this->_logger->Info('Administrator updated environment configuration', [
			'user_id' => (int)$user['id'],
			'keys' => array_values(array_filter(
				array_keys($values),
				static fn (string $key): bool => !in_array($key, ['PULSE_SMTP_PASSWORD', 'PULSE_CRON_TOKEN'], true)
			)),
			'smtp_password_changed' => array_key_exists('PULSE_SMTP_PASSWORD', $values),
			'cron_token_changed' => array_key_exists('PULSE_CRON_TOKEN', $values),
		]);
		$this->Flash('success', __('administration.settings.saved'));
		$this->Redirect('/administration?tab=' . rawurlencode($activeTab));
	}

	/** @brief Sends a test notification to the current administrator. */
	public function SendTestNotification(): void
	{
		$user = $this->RequireAdministrator();
		$status = $this->_testNotificationService->SendForUser($user);
		$key = match ($status)
		{
			'sent' => 'profile.notifications.test.sent',
			'retrying' => 'profile.notifications.test.retrying',
			'failed' => 'profile.notifications.test.failed',
			'disabled' => 'profile.notifications.test.disabled',
			default => 'profile.notifications.test.queued',
		};
		$type = $status === 'sent' ? 'success' : ($status === 'disabled' || $status === 'failed' ? 'error' : 'warning');
		$this->Flash($type, __($key));
		$this->Redirect('/administration?tab=mail#mail-operations');
	}

	/** @brief Requeues failed installation-wide mail jobs for another cron attempt. */
	public function RetryFailedNotifications(): void
	{
		$this->RequireAdministrator();
		$count = $this->_mailQueueRepository->RetryFailed(100);
		$this->Flash(
			$count > 0 ? 'success' : 'warning',
			__($count > 0 ? 'profile.notifications.retry.success' : 'profile.notifications.retry.none', ['count' => $count])
		);
		$this->Redirect('/administration?tab=mail#mail-queue');
	}

	/** @brief Clears safe unsent installation-wide mail jobs while debug mode is enabled. */
	public function ClearNotificationQueue(): void
	{
		$user = $this->RequireAdministrator();

		if (!$this->_debugEnabled)
		{
			http_response_code(404);
			exit;
		}

		$count = $this->_mailQueueRepository->ClearDebugQueue();
		$this->_logger->Warning('Administrator cleared safe debug mail queue', [
			'user_id' => (int)$user['id'],
			'cleared' => $count,
		]);
		$this->Flash(
			$count > 0 ? 'success' : 'warning',
			__($count > 0 ? 'profile.notifications.clear.success' : 'profile.notifications.clear.none', ['count' => $count])
		);
		$this->Redirect('/administration?tab=mail#mail-queue');
	}

	/**
	 * @brief Returns the effective editable settings in .env-compatible string form.
	 * @return array<string, string>
	 */
	private function CurrentSettings(): array
	{
		return [
			'PULSE_ENV' => Environment::Get('PULSE_ENV', 'production'),
			'PULSE_DEBUG' => Environment::GetBool('PULSE_DEBUG', false) ? 'true' : 'false',
			'PULSE_BASE_URL' => Environment::Get('PULSE_BASE_URL'),
			'PULSE_DISPLAY_TIMEZONE' => Environment::Get('PULSE_DISPLAY_TIMEZONE', 'Europe/Berlin'),
			'PULSE_DEFAULT_LOCALE' => Environment::Get('PULSE_DEFAULT_LOCALE', 'de'),
			'PULSE_TRUSTED_HOSTS' => Environment::Get('PULSE_TRUSTED_HOSTS'),
			'PULSE_COOKIE_SECURE' => Environment::GetBool('PULSE_COOKIE_SECURE', true) ? 'true' : 'false',
			'PULSE_COOKIE_SAMESITE' => Environment::Get('PULSE_COOKIE_SAMESITE', 'Strict'),
			'PULSE_SESSION_NAME' => Environment::Get('PULSE_SESSION_NAME', 'pulse_session'),
			'PULSE_SESSION_IDLE_TIMEOUT' => Environment::Get('PULSE_SESSION_IDLE_TIMEOUT', '1800'),
			'PULSE_SESSION_ABSOLUTE_TIMEOUT' => Environment::Get('PULSE_SESSION_ABSOLUTE_TIMEOUT', '43200'),
			'PULSE_SESSION_REGENERATION_INTERVAL' => Environment::Get('PULSE_SESSION_REGENERATION_INTERVAL', '900'),
			'PULSE_HSTS_ENABLED' => Environment::GetBool('PULSE_HSTS_ENABLED', true) ? 'true' : 'false',
			'PULSE_LOGIN_MAX_ATTEMPTS' => Environment::Get('PULSE_LOGIN_MAX_ATTEMPTS', '5'),
			'PULSE_LOGIN_WINDOW_SECONDS' => Environment::Get('PULSE_LOGIN_WINDOW_SECONDS', '900'),
			'PULSE_LOGIN_BLOCK_SECONDS' => Environment::Get('PULSE_LOGIN_BLOCK_SECONDS', '900'),
			'PULSE_PASSWORD_MINIMUM_LENGTH' => Environment::Get('PULSE_PASSWORD_MINIMUM_LENGTH', '12'),
			'PULSE_UPLOAD_MAXIMUM_BYTES' => Environment::Get('PULSE_UPLOAD_MAXIMUM_BYTES', '26214400'),
			'PULSE_UPLOAD_ALLOWED_MIME_TYPES' => Environment::Get('PULSE_UPLOAD_ALLOWED_MIME_TYPES', 'application/pdf,application/rtf,application/vnd.oasis.opendocument.text,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png,text/plain'),
			'PULSE_MAIL_ENABLED' => Environment::GetBool('PULSE_MAIL_ENABLED', false) ? 'true' : 'false',
			'PULSE_SMTP_HOST' => Environment::Get('PULSE_SMTP_HOST'),
			'PULSE_SMTP_PORT' => Environment::Get('PULSE_SMTP_PORT', '587'),
			'PULSE_SMTP_ENCRYPTION' => Environment::Get('PULSE_SMTP_ENCRYPTION', 'starttls'),
			'PULSE_SMTP_USERNAME' => Environment::Get('PULSE_SMTP_USERNAME'),
			'PULSE_SMTP_PASSWORD' => Environment::Get('PULSE_SMTP_PASSWORD') !== '' ? '__configured__' : '',
			'PULSE_MAIL_FROM_ADDRESS' => Environment::Get('PULSE_MAIL_FROM_ADDRESS'),
			'PULSE_MAIL_FROM_NAME' => Environment::Get('PULSE_MAIL_FROM_NAME', 'Pulse'),
			'PULSE_SMTP_TIMEOUT_SECONDS' => Environment::Get('PULSE_SMTP_TIMEOUT_SECONDS', '15'),
			'PULSE_MAIL_MAX_ATTEMPTS' => Environment::Get('PULSE_MAIL_MAX_ATTEMPTS', '5'),
			'PULSE_MAIL_RETRY_DELAYS_SECONDS' => Environment::Get('PULSE_MAIL_RETRY_DELAYS_SECONDS', '60,300,1800,7200'),
			'PULSE_MAIL_LEASE_SECONDS' => Environment::Get('PULSE_MAIL_LEASE_SECONDS', '120'),
			'PULSE_MAIL_WORKER_BATCH_SIZE' => Environment::Get('PULSE_MAIL_WORKER_BATCH_SIZE', '25'),
			'PULSE_CRON_TOKEN' => Environment::Get('PULSE_CRON_TOKEN') !== '' ? '__configured__' : '',
		];
	}

	/**
	 * @brief Collects posted editable settings, excluding secret fields handled separately.
	 * @return array<string, string>
	 */
	private function PostedSettings(): array
	{
		return [
			'PULSE_ENV' => strtolower($this->_request->PostString('PULSE_ENV', 32)),
			'PULSE_DEBUG' => $this->_request->PostBool('PULSE_DEBUG') ? 'true' : 'false',
			'PULSE_DISPLAY_TIMEZONE' => $this->_request->PostString('PULSE_DISPLAY_TIMEZONE', 128),
			'PULSE_DEFAULT_LOCALE' => $this->_request->PostString('PULSE_DEFAULT_LOCALE', 10),
			'PULSE_TRUSTED_HOSTS' => $this->NormalizeCsv($this->_request->PostString('PULSE_TRUSTED_HOSTS', 4096)),
			'PULSE_COOKIE_SECURE' => $this->_request->PostBool('PULSE_COOKIE_SECURE') ? 'true' : 'false',
			'PULSE_COOKIE_SAMESITE' => $this->_request->PostString('PULSE_COOKIE_SAMESITE', 16),
			'PULSE_SESSION_NAME' => $this->_request->PostString('PULSE_SESSION_NAME', 128),
			'PULSE_SESSION_IDLE_TIMEOUT' => $this->_request->PostString('PULSE_SESSION_IDLE_TIMEOUT', 16),
			'PULSE_SESSION_ABSOLUTE_TIMEOUT' => $this->_request->PostString('PULSE_SESSION_ABSOLUTE_TIMEOUT', 16),
			'PULSE_SESSION_REGENERATION_INTERVAL' => $this->_request->PostString('PULSE_SESSION_REGENERATION_INTERVAL', 16),
			'PULSE_HSTS_ENABLED' => $this->_request->PostBool('PULSE_HSTS_ENABLED') ? 'true' : 'false',
			'PULSE_LOGIN_MAX_ATTEMPTS' => $this->_request->PostString('PULSE_LOGIN_MAX_ATTEMPTS', 16),
			'PULSE_LOGIN_WINDOW_SECONDS' => $this->_request->PostString('PULSE_LOGIN_WINDOW_SECONDS', 16),
			'PULSE_LOGIN_BLOCK_SECONDS' => $this->_request->PostString('PULSE_LOGIN_BLOCK_SECONDS', 16),
			'PULSE_PASSWORD_MINIMUM_LENGTH' => $this->_request->PostString('PULSE_PASSWORD_MINIMUM_LENGTH', 16),
			'PULSE_UPLOAD_MAXIMUM_BYTES' => $this->_request->PostString('PULSE_UPLOAD_MAXIMUM_BYTES', 24),
			'PULSE_UPLOAD_ALLOWED_MIME_TYPES' => $this->NormalizeCsv($this->_request->PostString('PULSE_UPLOAD_ALLOWED_MIME_TYPES', 16384)),
			'PULSE_MAIL_ENABLED' => $this->_request->PostBool('PULSE_MAIL_ENABLED') ? 'true' : 'false',
			'PULSE_SMTP_HOST' => $this->_request->PostString('PULSE_SMTP_HOST', 255),
			'PULSE_SMTP_PORT' => $this->_request->PostString('PULSE_SMTP_PORT', 16),
			'PULSE_SMTP_ENCRYPTION' => strtolower($this->_request->PostString('PULSE_SMTP_ENCRYPTION', 16)),
			'PULSE_SMTP_USERNAME' => $this->_request->PostString('PULSE_SMTP_USERNAME', 255, false),
			'PULSE_MAIL_FROM_ADDRESS' => $this->_request->PostString('PULSE_MAIL_FROM_ADDRESS', 255),
			'PULSE_MAIL_FROM_NAME' => $this->_request->PostString('PULSE_MAIL_FROM_NAME', 255),
			'PULSE_SMTP_TIMEOUT_SECONDS' => $this->_request->PostString('PULSE_SMTP_TIMEOUT_SECONDS', 16),
			'PULSE_MAIL_MAX_ATTEMPTS' => $this->_request->PostString('PULSE_MAIL_MAX_ATTEMPTS', 16),
			'PULSE_MAIL_RETRY_DELAYS_SECONDS' => $this->NormalizeCsv($this->_request->PostString('PULSE_MAIL_RETRY_DELAYS_SECONDS', 4096)),
			'PULSE_MAIL_LEASE_SECONDS' => $this->_request->PostString('PULSE_MAIL_LEASE_SECONDS', 16),
			'PULSE_MAIL_WORKER_BATCH_SIZE' => $this->_request->PostString('PULSE_MAIL_WORKER_BATCH_SIZE', 16),
		];
	}

	/**
	 * @brief Validates a prospective .env configuration before it can make Pulse unbootable.
	 * @param array<string, string> $values Non-secret posted values.
	 * @param string $smtpPassword Effective SMTP password after the update.
	 * @param string $cronToken Effective cron token after the update.
	 */
	private function ValidateSettings(array $values, string $smtpPassword, string $cronToken): void
	{
		$environment = (string)$values['PULSE_ENV'];

		if (!in_array($environment, ['production', 'development', 'testing'], true))
		{
			throw new RuntimeException('PULSE_ENV must be production, development, or testing.');
		}

		$baseUrl = (string)$values['PULSE_BASE_URL'];

		if ($baseUrl === '')
		{
			throw new RuntimeException('PULSE_BASE_URL must not be empty.');
		}

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
			throw new RuntimeException('PULSE_BASE_URL must be a site origin without a path, query, credentials, or fragment.');
		}

		if ($environment === 'production' && strtolower((string)$parts['scheme']) !== 'https')
		{
			throw new RuntimeException('Production requires an HTTPS PULSE_BASE_URL.');
		}

		if ($environment === 'production' && (string)($this->_databaseConfig['password'] ?? '') === '')
		{
			throw new RuntimeException('Production requires a database password.');
		}

		try
		{
			new DateTimeZone((string)$values['PULSE_DISPLAY_TIMEZONE']);
		}
		catch (\Throwable $throwable)
		{
			throw new RuntimeException('PULSE_DISPLAY_TIMEZONE is invalid.', 0, $throwable);
		}

		if (!in_array((string)$values['PULSE_DEFAULT_LOCALE'], $this->_availableLocales, true))
		{
			throw new RuntimeException('PULSE_DEFAULT_LOCALE is not an installed Pulse language.');
		}

		$trustedHosts = array_values(array_filter(array_map('trim', explode(',', (string)$values['PULSE_TRUSTED_HOSTS']))));

		foreach ($trustedHosts as $host)
		{
			if (preg_match('/^[a-z0-9.-]+$/i', $host) !== 1)
			{
				throw new RuntimeException('PULSE_TRUSTED_HOSTS contains an invalid hostname.');
			}
		}

		if ($environment === 'production')
		{
			$baseHost = strtolower((string)$parts['host']);
			$trustedHostsLower = array_map('strtolower', $trustedHosts);

			if (!in_array($baseHost, $trustedHostsLower, true))
			{
				throw new RuntimeException('Production PULSE_TRUSTED_HOSTS must include the PULSE_BASE_URL host.');
			}

			if ((string)$values['PULSE_COOKIE_SECURE'] !== 'true')
			{
				throw new RuntimeException('Production session cookies must be Secure.');
			}
		}

		if (!in_array((string)$values['PULSE_COOKIE_SAMESITE'], ['Strict', 'Lax', 'None'], true))
		{
			throw new RuntimeException('PULSE_COOKIE_SAMESITE must be Strict, Lax, or None.');
		}

		if ((string)$values['PULSE_COOKIE_SAMESITE'] === 'None' && (string)$values['PULSE_COOKIE_SECURE'] !== 'true')
		{
			throw new RuntimeException('SameSite=None requires secure session cookies.');
		}

		if ((string)$values['PULSE_SESSION_NAME'] === '' || preg_match('/^[A-Za-z0-9_-]+$/', (string)$values['PULSE_SESSION_NAME']) !== 1)
		{
			throw new RuntimeException('PULSE_SESSION_NAME must contain only letters, numbers, underscores, and hyphens.');
		}

		$this->ValidateInteger($values, 'PULSE_SESSION_IDLE_TIMEOUT', 300, null);
		$this->ValidateInteger($values, 'PULSE_SESSION_ABSOLUTE_TIMEOUT', 1800, null);
		$this->ValidateInteger($values, 'PULSE_SESSION_REGENERATION_INTERVAL', 60, null);
		$this->ValidateInteger($values, 'PULSE_LOGIN_MAX_ATTEMPTS', 2, 50);
		$this->ValidateInteger($values, 'PULSE_LOGIN_WINDOW_SECONDS', 60, null);
		$this->ValidateInteger($values, 'PULSE_LOGIN_BLOCK_SECONDS', 60, null);
		$this->ValidateInteger($values, 'PULSE_PASSWORD_MINIMUM_LENGTH', 8, 128);
		$this->ValidateInteger($values, 'PULSE_UPLOAD_MAXIMUM_BYTES', 1024, null);

		if ((string)$values['PULSE_UPLOAD_ALLOWED_MIME_TYPES'] === '')
		{
			throw new RuntimeException('PULSE_UPLOAD_ALLOWED_MIME_TYPES must contain at least one MIME type.');
		}

		$mailEnabled = (string)$values['PULSE_MAIL_ENABLED'] === 'true';
		$encryption = (string)$values['PULSE_SMTP_ENCRYPTION'];

		if (!in_array($encryption, ['starttls', 'tls', 'none'], true))
		{
			throw new RuntimeException('PULSE_SMTP_ENCRYPTION must be starttls, tls, or none.');
		}

		$this->ValidateInteger($values, 'PULSE_SMTP_PORT', 1, 65535);
		$this->ValidateInteger($values, 'PULSE_SMTP_TIMEOUT_SECONDS', 2, 120);
		$this->ValidateInteger($values, 'PULSE_MAIL_MAX_ATTEMPTS', 1, 20);
		$this->ValidateInteger($values, 'PULSE_MAIL_LEASE_SECONDS', 30, 1800);
		$this->ValidateInteger($values, 'PULSE_MAIL_WORKER_BATCH_SIZE', 1, 250);

		$retryDelays = array_values(array_filter(explode(',', (string)$values['PULSE_MAIL_RETRY_DELAYS_SECONDS']), static fn (string $item): bool => $item !== ''));

		if ($retryDelays === [])
		{
			throw new RuntimeException('PULSE_MAIL_RETRY_DELAYS_SECONDS must contain at least one delay.');
		}

		foreach ($retryDelays as $delay)
		{
			if (preg_match('/^\d+$/', $delay) !== 1)
			{
				throw new RuntimeException('PULSE_MAIL_RETRY_DELAYS_SECONDS must contain comma-separated non-negative integers.');
			}
		}

		if ($mailEnabled)
		{
			$host = (string)$values['PULSE_SMTP_HOST'];

			if ($host === '')
			{
				throw new RuntimeException('PULSE_SMTP_HOST is required when mail is enabled.');
			}

			if (
				filter_var($host, FILTER_VALIDATE_IP) === false
				&& preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $host) !== 1
			)
			{
				throw new RuntimeException('PULSE_SMTP_HOST must be a valid hostname or IP address.');
			}

			if ($environment === 'production' && $encryption === 'none')
			{
				throw new RuntimeException('Production SMTP delivery requires TLS or STARTTLS.');
			}

			if (filter_var((string)$values['PULSE_MAIL_FROM_ADDRESS'], FILTER_VALIDATE_EMAIL) === false)
			{
				throw new RuntimeException('PULSE_MAIL_FROM_ADDRESS must be a valid email address.');
			}

			$username = (string)$values['PULSE_SMTP_USERNAME'];

			if (($username === '') !== ($smtpPassword === ''))
			{
				throw new RuntimeException('PULSE_SMTP_USERNAME and PULSE_SMTP_PASSWORD must either both be set or both be empty.');
			}
		}

		if (strpbrk((string)$values['PULSE_MAIL_FROM_NAME'], "\r\n") !== false)
		{
			throw new RuntimeException('PULSE_MAIL_FROM_NAME must not contain line breaks.');
		}

		if ($cronToken !== '' && strlen($cronToken) < 32)
		{
			throw new RuntimeException('PULSE_CRON_TOKEN must contain at least 32 characters.');
		}
	}

	/**
	 * @brief Validates one integer-form environment value.
	 * @param array<string, string> $values Settings map.
	 * @param string $key Setting name.
	 * @param int|null $minimum Minimum accepted value.
	 * @param int|null $maximum Maximum accepted value.
	 */
	private function ValidateInteger(array $values, string $key, ?int $minimum, ?int $maximum): void
	{
		$value = filter_var($values[$key] ?? null, FILTER_VALIDATE_INT);

		if ($value === false)
		{
			throw new RuntimeException($key . ' must be an integer.');
		}

		if ($minimum !== null && (int)$value < $minimum)
		{
			throw new RuntimeException($key . ' must be at least ' . $minimum . '.');
		}

		if ($maximum !== null && (int)$value > $maximum)
		{
			throw new RuntimeException($key . ' must not exceed ' . $maximum . '.');
		}
	}

	/**
	 * @brief Returns actionable configuration issues grouped by administration tab.
	 * @param array<string, string> $settings Effective settings.
	 * @param array<int, string> $processOverrides Process-environment override keys.
	 * @param string $cronStatus Cron runtime status.
	 * @return array<int, array{tab:string,key:string,type:string}>
	 */
	private function ConfigurationIssues(array $settings, array $processOverrides, string $cronStatus): array
	{
		$issues = [];

		if (!$this->_environmentFile->IsWritable())
		{
			$issues[] = ['tab' => 'general', 'key' => 'administration.health.env_not_writable', 'type' => 'error'];
		}

		if ((string)$settings['PULSE_BASE_URL'] === '')
		{
			$issues[] = ['tab' => 'installation', 'key' => 'administration.health.base_url_missing', 'type' => 'warning'];
		}

		if (!$this->_mailEnabled)
		{
			$issues[] = ['tab' => 'mail', 'key' => 'administration.health.mail_disabled', 'type' => 'warning'];
		}

		if (Environment::Get('PULSE_CRON_TOKEN') === '')
		{
			$issues[] = ['tab' => 'cron', 'key' => 'administration.health.cron_token_missing', 'type' => 'warning'];
		}

		if ($cronStatus === 'never')
		{
			$issues[] = ['tab' => 'cron', 'key' => 'administration.health.cron_never_run', 'type' => 'warning'];
		}
		else if ($cronStatus === 'stale')
		{
			$issues[] = ['tab' => 'cron', 'key' => 'administration.health.cron_stale', 'type' => 'warning'];
		}

		if ($processOverrides !== [])
		{
			$issues[] = ['tab' => 'installation', 'key' => 'administration.health.process_overrides', 'type' => 'warning'];
		}

		return $issues;
	}

	/**
	 * @brief Classifies the latest successful combined cron run without assuming a short cadence.
	 * @param string|null $lastSuccessfulCronRun UTC database timestamp.
	 * @return string One of never, recent, or stale.
	 */
	private function CronStatus(?string $lastSuccessfulCronRun): string
	{
		if ($lastSuccessfulCronRun === null || trim($lastSuccessfulCronRun) === '')
		{
			return 'never';
		}

		try
		{
			$lastRun = new DateTimeImmutable($lastSuccessfulCronRun, new DateTimeZone('UTC'));
			$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

			return ($now->getTimestamp() - $lastRun->getTimestamp()) > 86400 ? 'stale' : 'recent';
		}
		catch (\Throwable)
		{
			return 'never';
		}
	}

	/**
	 * @brief Finds editable settings overridden by the web-server process environment.
	 * @param array<int, string> $keys Managed setting names.
	 * @return array<int, string>
	 */
	private function ProcessOverrides(array $keys): array
	{
		return array_values(array_filter($keys, static fn (string $key): bool => is_string(getenv($key))));
	}

	/** @brief Normalizes comma-separated text without changing item content. */
	private function NormalizeCsv(string $value): string
	{
		$items = array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));
		return implode(',', $items);
	}

	/** @brief Restricts navigation state to known administration tabs. */
	private function NormalizeTab(string $tab): string
	{
		return in_array($tab, self::TABS, true) ? $tab : 'general';
	}
}
