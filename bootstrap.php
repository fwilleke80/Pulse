<?php

/**
 * @file bootstrap.php
 * @brief Builds the Pulse service container for web and command-line entry points.
 * @author Frank Willeke
 */

declare(strict_types=1);

use Pulse\Core\CsrfTokenManager;
use Pulse\Core\ConfigurationValidator;
use Pulse\Core\Database;
use Pulse\Core\Environment;
use Pulse\Core\EnvironmentFile;
use Pulse\Core\ErrorHandler;
use Pulse\Core\Logger;
use Pulse\Core\LanguageCatalog;
use Pulse\Core\WebsiteLanguagePreference;
use Pulse\Core\MigrationRunner;
use Pulse\Core\NotificationLanguage;
use Pulse\Core\Request;
use Pulse\Core\Router;
use Pulse\Core\Session;
use Pulse\Core\Translator;
use Pulse\Core\View;
use Pulse\Mail\SmtpMailTransport;
use Pulse\Repositories\ContactRepository;
use Pulse\Repositories\DocumentRepository;
use Pulse\Repositories\LoginThrottleRepository;
use Pulse\Repositories\MailQueueRepository;
use Pulse\Repositories\SystemStatusRepository;
use Pulse\Repositories\MessageRepository;
use Pulse\Repositories\MonitorRepository;
use Pulse\Repositories\RecipientRepository;
use Pulse\Repositories\RecipientPortalRepository;
use Pulse\Repositories\UserRepository;
use Pulse\Services\AuthService;
use Pulse\Services\DocumentService;
use Pulse\Services\EscalationService;
use Pulse\Services\LoginThrottleService;
use Pulse\Services\MailQueueWorker;
use Pulse\Services\MonitorExecutionService;
use Pulse\Services\MonitorStateMachine;
use Pulse\Services\NotificationComposer;
use Pulse\Services\NotificationScheduler;
use Pulse\Services\RecipientPortalArchiveBuilder;
use Pulse\Services\RecipientPortalService;
use Pulse\Services\TestNotificationService;

$composerAutoloader = __DIR__ . '/vendor/autoload.php';

if (is_file($composerAutoloader))
{
	require_once $composerAutoloader;
}
else
{
	spl_autoload_register(function (string $class): void
	{
		$prefix = 'Pulse\\';

		if (!str_starts_with($class, $prefix))
		{
			return;
		}

		$file = __DIR__ . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

		if (is_file($file))
		{
			require_once $file;
		}
	});

	require_once __DIR__ . '/app/Core/helpers.php';
}

date_default_timezone_set('UTC');

$logger = new Logger(__DIR__ . '/storage/logs/app.log');
$errorHandler = new ErrorHandler($logger, false);
$errorHandler->Register();

Environment::Load(__DIR__ . '/.env');
$environmentFile = new EnvironmentFile(__DIR__ . '/.env');

$appConfig = require __DIR__ . '/config/app.php';
$languagePath = __DIR__ . '/app/Lang';
$languageCatalog = new LanguageCatalog($languagePath, 'en');
$appConfig['available_locales'] = $languageCatalog->Locales();
$dbConfig = require __DIR__ . '/config/database.php';
$errorHandler->SetDebug((bool)$appConfig['debug']);
ConfigurationValidator::Validate($appConfig, $dbConfig);
$versionFile = __DIR__ . '/config/version.php';
$appVersion = '';

if (is_file($versionFile))
{
	$generatedVersion = require $versionFile;
	$appVersion = is_string($generatedVersion) ? trim($generatedVersion) : '';
}

$request = Request::FromGlobals();
$session = new Session((array)$appConfig['session']);
$isCli = PHP_SAPI === 'cli';
$isBackgroundRequest = defined('PULSE_BACKGROUND_REQUEST') && PULSE_BACKGROUND_REQUEST === true;

if (!$isCli && !$isBackgroundRequest)
{
	$session->Start();
}
$csrf = new CsrfTokenManager($session);

$database = new Database($dbConfig);
$migrationRunner = new MigrationRunner($database, __DIR__ . '/database/migrations');
$appliedMigrations = $migrationRunner->Migrate();

if ($appliedMigrations !== [])
{
	$logger->Info('Applied database migrations', ['migrations' => $appliedMigrations]);
}

$router = new Router();
$view = new View(__DIR__ . '/app/Views');
$userRepository = new UserRepository($database);
$contactRepository = new ContactRepository($database);
$monitorRepository = new MonitorRepository($database);
$recipientRepository = new RecipientRepository($database);
$recipientPortalRepository = new RecipientPortalRepository($database);
$documentRepository = new DocumentRepository($database);
$messageRepository = new MessageRepository($database);
$mailQueueRepository = new MailQueueRepository($database);
$systemStatusRepository = new SystemStatusRepository($database);
$loginThrottleRepository = new LoginThrottleRepository($database);
$loginThrottle = new LoginThrottleService($loginThrottleRepository, (array)$appConfig['security']);
$monitorStateMachine = new MonitorStateMachine();
$monitorExecutionService = new MonitorExecutionService($database, $monitorStateMachine, $logger);
$auth = new AuthService($userRepository, $session, $logger);
$documentService = new DocumentService(
	$documentRepository,
	$monitorRepository,
	$logger,
	__DIR__ . '/storage/uploads/monitor-documents',
	(array)$appConfig['uploads']
);

$defaultLocale = (string)$appConfig['locale'];
$availableLocales = $languageCatalog->Locales();

if (!$languageCatalog->Has($defaultLocale))
{
	throw new RuntimeException('PULSE_DEFAULT_LOCALE does not have a matching app/Lang/' . $defaultLocale . '.php file.');
}

$notificationLanguage = new NotificationLanguage($availableLocales, $defaultLocale);
$cookieLocale = WebsiteLanguagePreference::Read();
$preferredLocale = is_string($cookieLocale) && in_array($cookieLocale, $availableLocales, true) ? $cookieLocale : $defaultLocale;
$locale = $isCli || $isBackgroundRequest ? $defaultLocale : $session->Get('locale', $preferredLocale);

if (!is_string($locale) || !in_array($locale, $availableLocales, true))
{
	$locale = $defaultLocale;
}

$translator = new Translator($languagePath, $locale, $languageCatalog->FallbackLocale());
$notificationComposer = new NotificationComposer($notificationLanguage, $languagePath, $appConfig);
$recipientPortalArchiveBuilder = new RecipientPortalArchiveBuilder($documentService);
$recipientPortalService = new RecipientPortalService(
	$recipientPortalRepository,
	$mailQueueRepository,
	$notificationComposer,
	$logger,
	(int)$appConfig['mail']['max_attempts']
);
$escalationService = new EscalationService(
	$database,
	$monitorStateMachine,
	$notificationComposer,
	$logger,
	(int)$appConfig['mail']['max_attempts']
);
$smtpConfig = (array)$appConfig['mail'];
$smtpConfig['base_url'] = (string)$appConfig['base_url'];
$mailTransport = new SmtpMailTransport($smtpConfig);
$mailQueueWorker = new MailQueueWorker(
	$mailQueueRepository,
	$mailTransport,
	$logger,
	(int)$appConfig['mail']['lease_seconds'],
	(array)$appConfig['mail']['retry_delays_seconds']
);
$notificationScheduler = new NotificationScheduler(
	$database,
	$monitorExecutionService,
	$mailQueueRepository,
	$notificationComposer,
	$escalationService,
	$logger,
	(int)$appConfig['mail']['max_attempts']
);
$testNotificationService = new TestNotificationService(
	$mailQueueRepository,
	$notificationComposer,
	$mailQueueWorker,
	(bool)$appConfig['mail']['enabled'],
	(int)$appConfig['mail']['max_attempts']
);
setTranslator($translator);
setLanguageCatalog($languageCatalog);
setCsrfTokenManager($csrf);
setNotificationLanguageResolver($notificationLanguage);
setDisplayTimezone((string)$appConfig['display_timezone']);

$view->SetGlobals([
	'appName' => $appConfig['name'],
	'appVersion' => $appVersion,
	'isAuthenticated' => $isCli || $isBackgroundRequest ? false : $auth->IsAuthenticated(),
	'currentUser' => $isCli || $isBackgroundRequest ? null : $auth->GetCurrentUser(),
	'locale' => $locale,
	'base_url' => $appConfig['base_url'],
	'currentTarget' => $request->Target(),
	'availableLocales' => $availableLocales,
]);

return [
	'config' => $appConfig,
	'dbConfig' => $dbConfig,
	'environmentFile' => $environmentFile,
	'db' => $database,
	'appliedMigrations' => $appliedMigrations,
	'router' => $router,
	'view' => $view,
	'request' => $request,
	'session' => $session,
	'csrf' => $csrf,
	'userRepository' => $userRepository,
	'auth' => $auth,
	'translator' => $translator,
	'languageCatalog' => $languageCatalog,
	'notificationLanguage' => $notificationLanguage,
	'notificationComposer' => $notificationComposer,
	'recipientPortalService' => $recipientPortalService,
	'recipientPortalArchiveBuilder' => $recipientPortalArchiveBuilder,
	'logger' => $logger,
	'contactRepository' => $contactRepository,
	'monitorRepository' => $monitorRepository,
	'recipientRepository' => $recipientRepository,
	'recipientPortalRepository' => $recipientPortalRepository,
	'documentRepository' => $documentRepository,
	'messageRepository' => $messageRepository,
	'mailQueueRepository' => $mailQueueRepository,
	'systemStatusRepository' => $systemStatusRepository,
	'monitorExecutionService' => $monitorExecutionService,
	'documentService' => $documentService,
	'loginThrottle' => $loginThrottle,
	'mailQueueWorker' => $mailQueueWorker,
	'notificationScheduler' => $notificationScheduler,
	'escalationService' => $escalationService,
	'testNotificationService' => $testNotificationService,
];
