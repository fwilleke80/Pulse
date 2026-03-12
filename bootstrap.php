<?php

declare(strict_types=1);

use Pulse\Core\Database;
use Pulse\Core\Router;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Repositories\UserRepository;
use Pulse\Services\AuthService;
use Pulse\Core\Translator;
use ErrorException;


// ----- Autoload classes using PSR-4 standard -----
spl_autoload_register(function (string $class): void
{
	$prefix = 'Pulse\\';
	$baseDir = __DIR__ . '/app/';

	if (strncmp($prefix, $class, strlen($prefix)) !== 0)
	{
		return;
	}

	$relativeClass = substr($class, strlen($prefix));
	$file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

	if (is_file($file))
	{
		require_once $file;
	}
});

// ----- Load helper functions -----
require_once __DIR__ . '/app/Core/helpers.php';

// ----- Load configuration -----
$appConfig = require __DIR__ . '/config/app.php';
$dbConfig = require __DIR__ . '/config/database.php';

// ----- Set up error handling -----
if ($appConfig['debug'] === true)
{
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');

	set_error_handler(function (
		int $severity,
		string $message,
		string $file,
		int $line
	): bool
	{
		throw new ErrorException($message, 0, $severity, $file, $line);
	});

	set_exception_handler(function (Throwable $throwable): void
	{
		http_response_code(500);
		header('Content-Type: text/html; charset=utf-8');

		echo '<!DOCTYPE html>';
		echo '<html><head><meta charset="utf-8">';
		echo '<title>Pulse Debug Error</title>';
		echo '<style>
			body { font-family: monospace; background: #f6f6f6; margin: 2rem; }
			.error { background: #fff; border: 1px solid #ddd; padding: 1.5rem; border-radius: 8px; }
			h1 { margin-top: 0; }
			pre { background: #111; color: #eee; padding: 1rem; overflow: auto; white-space: pre-wrap; }
		</style>';
		echo '</head><body><div class="error">';
		echo '<h1>Unhandled Exception</h1>';

		echo '<p><strong>Message:</strong><br>';
		echo htmlspecialchars($throwable->getMessage(), ENT_QUOTES, 'UTF-8');
		echo '</p>';

		echo '<p><strong>File:</strong><br>';
		echo htmlspecialchars($throwable->getFile(), ENT_QUOTES, 'UTF-8');
		echo ' : ' . $throwable->getLine();
		echo '</p>';

		echo '<p><strong>Stack trace:</strong></p>';
		echo '<pre>';
		echo htmlspecialchars($throwable->getTraceAsString(), ENT_QUOTES, 'UTF-8');
		echo '</pre>';

		echo '</div></body></html>';
	});

	register_shutdown_function(function (): void
	{
		$error = error_get_last();

		if ($error === null)
		{
			return;
		}

		$fatalTypes = [
			E_ERROR,
			E_PARSE,
			E_CORE_ERROR,
			E_COMPILE_ERROR,
			E_USER_ERROR,
		];

		if (!in_array($error['type'], $fatalTypes, true))
		{
			return;
		}

		http_response_code(500);
		header('Content-Type: text/html; charset=utf-8');

		echo '<!DOCTYPE html>';
		echo '<html><head><meta charset="utf-8">';
		echo '<title>Pulse Fatal Error</title>';
		echo '<style>
			body { font-family: monospace; background: #f6f6f6; margin: 2rem; }
			.error { background: #fff; border: 1px solid #ddd; padding: 1.5rem; border-radius: 8px; }
			h1 { margin-top: 0; }
			pre { background: #111; color: #eee; padding: 1rem; overflow: auto; white-space: pre-wrap; }
		</style>';
		echo '</head><body><div class="error">';
		echo '<h1>Fatal Error</h1>';

		echo '<p><strong>Message:</strong><br>';
		echo htmlspecialchars((string)$error['message'], ENT_QUOTES, 'UTF-8');
		echo '</p>';

		echo '<p><strong>File:</strong><br>';
		echo htmlspecialchars((string)$error['file'], ENT_QUOTES, 'UTF-8');
		echo ' : ' . (int)$error['line'];
		echo '</p>';

		echo '</div></body></html>';
	});
}
else
{
	error_reporting(E_ALL);
	ini_set('display_errors', '0');
}

// ----- Set default timezone -----
date_default_timezone_set($appConfig['timezone']);

// ----- Initialize services -----
$database = new Database($dbConfig);
$router = new Router();
$view = new View(__DIR__ . '/app/Views');
$session = new Session();
$userRepository = new UserRepository($database);
$auth = new AuthService($userRepository, $session);

// ----- Start session -----
$session->Start();

// ----- Set up translator -----
$defaultLocale = (string)$appConfig['locale'];
$availableLocales = $appConfig['available_locales'];

$locale = $_SESSION['pulse_locale'] ?? $defaultLocale;

if (!in_array($locale, $availableLocales, true))
{
	$locale = $defaultLocale;
}

$translator = new Translator(__DIR__ . '/app/Lang', $locale);
setTranslator($translator);

// ----- Set global variables for views -----
$view->SetGlobals([
	'appName' => $appConfig['name'],
	'appVersion' => $appConfig['version'],
]);

// ----- Return all services in an array for easy access -----
return [
	'config' => $appConfig,
	'db' => $database,
	'router' => $router,
	'view' => $view,
	'session' => $session,
	'userRepository' => $userRepository,
	'auth' => $auth,
	'translator' => $translator,
];