<?php

declare(strict_types=1);

use RuntimeException;

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
		$session->SetFlash('error', 'Please enter your email address and password.');
		header('Location: /login');
		exit;
	}

	if (!$auth->Login($email, $password))
	{
		$session->SetFlash('error', 'Login failed.');
		header('Location: /login');
		exit;
	}

	$session->SetFlash('success', 'Login successful.');
	header('Location: /');
	exit;
});

$router->Get('/logout', function () use ($auth): void
{
	$auth->Logout();
	header('Location: /login');
	exit;
});

$router->Get('/health', function () use ($db): void
{
	http_response_code($db->CanConnect() ? 200 : 500);
	header('Content-Type: text/plain; charset=utf-8');
	echo $db->CanConnect() ? 'OK' : 'ERROR';
});

$router->Get('/imprint', function () use ($view, $config, $auth): string
{
	return $view->Render('static.imprint', [
		'appName' => $config['name'],
		'isAuthenticated' => $auth->IsAuthenticated(),
	]);
});

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
catch (Throwable)
{
	http_response_code(500);
	header('Content-Type: text/plain; charset=utf-8');
	echo 'Internal Server Error';
}