<?php

declare(strict_types=1);

use RuntimeException;

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
		'appName' => $config['name'],
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
		'appName' => $config['name'],
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
$router->Get('/health', function () use ($db): void
{
	http_response_code($db->CanConnect() ? 200 : 500);
	header('Content-Type: text/plain; charset=utf-8');
	echo $db->CanConnect() ? 'OK' : 'ERROR';
});

// ----- Static imprint page -----
$router->Get('/imprint', function () use ($view, $config, $auth): string
{
	return $view->Render('static.imprint', [
		'appName' => $config['name'],
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
catch (RuntimeException)
{
	http_response_code(404);
	echo $view->Render('home.not-found', [
		'appName' => $config['name'],
	]);
}