<?php

declare(strict_types=1);

use RuntimeException;
use Pulse\Core\NotFoundException;

// Load dependencies and initialize services
$container = require dirname(__DIR__) . '/bootstrap.php';

/** @var Pulse\Core\Database $db */
$db = $container['db'];

/** @var Pulse\Core\Router $router */
$router = $container['router'];

/** @var Pulse\Core\View $view */
$view = $container['view'];

/** @var Pulse\Core\Session $session */
$session = $container['session'];

/** @var Pulse\Services\AuthService $auth */
$auth = $container['auth'];

$config = $container['config'];

// ----- Define routes and dispatch request -----

// ----- Standard route -----
$router->Get('/', function () use ($auth, $session, $view, $config): string
{
	if (!$auth->IsAuthenticated())
	{
		header('Location: /login');
		exit;
	}

	$user = $auth->GetCurrentUser();
	$flash = $session->PullFlash();

	return $view->Render('home.dashboard', [
		'user' => $user,
		'flash' => $flash,
		'isAuthenticated' => true,
	]);
});

// ----- Authentication routes -----
$router->Get('/login', function () use ($auth, $session, $view, $config): string
{
	if ($auth->IsAuthenticated())
	{
		header('Location: /');
		exit;
	}

	$flash = $session->PullFlash();

	return $view->Render('auth.login', [
		'flash' => $flash,
	]);
});

$router->Post('/login', function () use ($auth, $session): void
{
	$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
	$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

	if ($email === '' || $password === '')
	{
		$session->SetFlash('error', e__('flash.login.required'));
		header('Location: /login');
		exit;
	}

	if (!$auth->Login($email, $password))
	{
		$session->SetFlash('error', e__('flash.login.failed'));
		header('Location: /login');
		exit;
	}

	$session->SetFlash('success', e__('flash.login.successful'));
	header('Location: /');
	exit;
});

$router->Get('/logout', function () use ($auth): void
{
	$auth->Logout();
	header('Location: /login');
	exit;
});

// ----- Health check route -----
$router->Get('/health', function () use ($db, $config): void
{
	$databaseOk = $db->CanConnect();

	$configOk =
		isset($config['name']) &&
		isset($config['timezone']);

	$storageDirs = [
		'storage' => dirname(__DIR__) . '/storage',
		'logs' => dirname(__DIR__) . '/storage/logs',
		'uploads' => dirname(__DIR__) . '/storage/uploads',
		'tmp' => dirname(__DIR__) . '/storage/tmp',
	];

	$directories = [];

	foreach ($storageDirs as $name => $path)
	{
		$directories[$name] = is_dir($path) && is_writable($path);
	}

	$directoriesOk = !in_array(false, $directories, true);

	$status = ($databaseOk && $directoriesOk && $configOk) ? 'ok' : 'error';

	http_response_code($status === 'ok' ? 200 : 500);

	header('Content-Type: application/json; charset=utf-8');

	echo json_encode(
		[
			'status' => $status,
			'checks' => [
				'liveness' => 'ok',
				'config' => $configOk ? 'ok' : 'error',
				'database' => $databaseOk ? 'ok' : 'error',
				'directories' => $directories,
			],
			'php' => PHP_VERSION,
			'time' => gmdate('c'),
		],
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	);
});

// ----- Static imprint page -----
$router->Get('/imprint', function () use ($view, $config, $auth): string
{
	return $view->Render('static.imprint', [
		'isAuthenticated' => $auth->IsAuthenticated(),
	]);
});

// ----- Language switcher route -----
$router->Get('/language/set', function () use ($config, $session): void
{
	$locale = isset($_GET['locale']) ? (string)$_GET['locale'] : '';
	$availableLocales = $config['available_locales'] ?? [];

	if (in_array($locale, $availableLocales, true))
	{
		$_SESSION['pulse_locale'] = $locale;
	}

	$session->SetFlash('success', e__('flash.languageswitched') . ' ' . htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'));
	$redirect = $_SERVER['HTTP_REFERER'] ?? '/';
	header('Location: ' . $redirect);
	exit;
});

// ----- Dispatch request -----
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH);

if (!is_string($requestPath) || $requestPath === '')
{
	$requestPath = '/';
}

try
{
	echo $router->Dispatch($requestMethod, $requestPath);
}
catch (NotFoundException)
{
	http_response_code(404);
	echo $view->Render('home.not-found');
}