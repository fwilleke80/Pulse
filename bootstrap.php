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
use Pulse\Core\ErrorHandler;
use Pulse\Core\Logger;
use Pulse\Core\MigrationRunner;
use Pulse\Core\Request;
use Pulse\Core\Router;
use Pulse\Core\Session;
use Pulse\Core\Translator;
use Pulse\Core\View;
use Pulse\Repositories\ContactRepository;
use Pulse\Repositories\DocumentRepository;
use Pulse\Repositories\LoginThrottleRepository;
use Pulse\Repositories\MessageRepository;
use Pulse\Repositories\MonitorRepository;
use Pulse\Repositories\UserRepository;
use Pulse\Services\AuthService;
use Pulse\Services\DocumentService;
use Pulse\Services\LoginThrottleService;
use Pulse\Services\MonitorExecutionService;
use Pulse\Services\MonitorStateMachine;

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

$appConfig = require __DIR__ . '/config/app.php';
$dbConfig = require __DIR__ . '/config/database.php';
$errorHandler->SetDebug((bool)$appConfig['debug']);
ConfigurationValidator::Validate($appConfig, $dbConfig);
$versionFile = __DIR__ . '/config/version.php';
$appVersion = is_file($versionFile) ? require $versionFile : 'dev';

$request = Request::FromGlobals();
$session = new Session((array)$appConfig['session']);
$session->Start();
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
$documentRepository = new DocumentRepository($database);
$messageRepository = new MessageRepository($database);
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
$availableLocales = (array)$appConfig['available_locales'];
$locale = $session->Get('locale', $defaultLocale);

if (!is_string($locale) || !in_array($locale, $availableLocales, true))
{
	$locale = $defaultLocale;
}

$translator = new Translator(__DIR__ . '/app/Lang', $locale);
setTranslator($translator);
setCsrfTokenManager($csrf);
setDisplayTimezone((string)$appConfig['display_timezone']);

$view->SetGlobals([
	'appName' => $appConfig['name'],
	'appVersion' => $appVersion,
	'isAuthenticated' => $auth->IsAuthenticated(),
	'currentUser' => $auth->GetCurrentUser(),
	'locale' => $locale,
	'base_url' => $appConfig['base_url'],
	'currentTarget' => $request->Target(),
]);

return [
	'config' => $appConfig,
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
	'logger' => $logger,
	'contactRepository' => $contactRepository,
	'monitorRepository' => $monitorRepository,
	'documentRepository' => $documentRepository,
	'messageRepository' => $messageRepository,
	'monitorExecutionService' => $monitorExecutionService,
	'documentService' => $documentService,
	'loginThrottle' => $loginThrottle,
];
