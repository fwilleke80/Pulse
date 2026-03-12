<?php

declare(strict_types=1);

use Pulse\Controllers\HomeController;
use Pulse\Controllers\AuthController;
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

/** @var Pulse\Repositories\ContactRepository $contactRepository */
$contactRepository = $container['contactRepository'];

$config = $container['config'];

// ----- Set global variables for views -----
$view->SetGlobals([
	'flash' => $session->PullFlash(),
], true);

// ----- Initialize controllers -----
$homeController = new HomeController($view, $session, $auth, $db, $config);
$authController = new AuthController($view, $session, $auth);

// ----- Standard route -----
$router->Get('/', fn (): string => $homeController->Dashboard());

// ----- Contact management routes -----
$router->Get('/contacts', function () use ($auth, $session, $view, $contactRepository): string
{
	if (!$auth->IsAuthenticated())
	{
		header('Location: /login');
		exit;
	}

	$user = $auth->GetCurrentUser();

	if (!is_array($user))
	{
		header('Location: /login');
		exit;
	}

	$contacts = $contactRepository->FindAllByUserId((int)$user['id']);

	return $view->Render('contacts.index', [
		'contacts' => $contacts,
		'user' => $user,
	]);
});

$router->Get('/contacts/new', function () use ($auth, $session, $view, $contactRepository): string
{
	if (!$auth->IsAuthenticated())
	{
		header('Location: /login');
		exit;
	}

	$user = $auth->GetCurrentUser();
	$contacts = $contactRepository->FindAllByUserId((int)$user['id']);

	return $view->Render('contacts.new', [
		'contacts' => $contacts,
		'user' => $user,
	]);
});

$router->Post('/contacts/create', function () use ($auth, $session, $contactRepository): void
{
	if (!$auth->IsAuthenticated())
	{
		header('Location: /login');
		exit;
	}

	$user = $auth->GetCurrentUser();

	if (!is_array($user))
	{
		header('Location: /login');
		exit;
	}

	$name = trim((string)($_POST['name'] ?? ''));
	$email = trim((string)($_POST['email'] ?? ''));
	$cellPhone = trim((string)($_POST['cell_phone'] ?? ''));
	$notes = trim((string)($_POST['notes'] ?? ''));

	if ($name === '' || $email === '')
	{
		$session->SetFlash('error', 'Name and email are required.');
		header('Location: /contacts/new');
		exit;
	}

	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
	{
		$session->SetFlash('error', 'Please enter a valid email address.');
		header('Location: /contacts/new');
		exit;
	}

	$contactRepository->CreateForUser(
		(int)$user['id'],
		$name,
		$email,
		$cellPhone !== '' ? $cellPhone : null,
		$notes !== '' ? $notes : null
	);

	$session->SetFlash('success', 'Contact created.');
	header('Location: /contacts');
	exit;
});

$router->Post('/contacts/delete', function () use ($auth, $session, $contactRepository): void
{
	if (!$auth->IsAuthenticated())
	{
		header('Location: /login');
		exit;
	}

	$user = $auth->GetCurrentUser();

	if (!is_array($user))
	{
		header('Location: /login');
		exit;
	}

	$contactId = (int)($_POST['id'] ?? 0);

	if ($contactId > 0)
	{
		$contactRepository->DeleteForUser($contactId, (int)$user['id']);
		$session->SetFlash('success', 'Contact deleted.');
	}

	header('Location: /contacts');
	exit;
});

// ----- Authentication routes -----
$router->Get('/login', fn (): string => $authController->ShowLogin());
$router->Post('/login', fn () => $authController->Login());
$router->Get('/logout', fn () => $authController->Logout());

// ----- Health check route -----
$router->Get('/health', fn () => $homeController->Health());

// ----- Static imprint page -----
$router->Get('/imprint', fn (): string => $homeController->Imprint());

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