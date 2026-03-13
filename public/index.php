<?php

declare(strict_types=1);

use Pulse\Controllers\HomeController;
use Pulse\Controllers\AuthController;
use Pulse\Controllers\ContactController;
use Pulse\Controllers\LanguageController;
use Pulse\Controllers\ProfileController;
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

/** @var Pulse\Core\Translator $translator */
$translator = $container['translator'];

/** @var Pulse\Repositories\UserRepository $userRepository */
$userRepository = $container['userRepository'];

$config = $container['config'];

// ----- Set global variables for views -----
$view->SetGlobals([
	'flash' => $session->PullFlash(),
], true);

// ----- Initialize controllers -----
$homeController = new HomeController($view, $session, $auth, $db, $config);
$authController = new AuthController($view, $session, $auth);
$contactController = new ContactController($view, $session, $auth, $contactRepository);
$languageController = new LanguageController($view, $session, $auth, $translator, $config['supported_locales'] ?? ['en', 'de']);
$profileController = new ProfileController($view, $session, $auth, $userRepository);

// ----- Home routes -----
$router->Get('/', [$homeController, 'Dashboard']);
$router->Get('/imprint', [$homeController, 'Imprint']);
$router->Get('/health', [$homeController, 'Health']);

// ----- Contact management routes -----
$router->Get('/contacts', [$contactController, 'Index']);
$router->Get('/contacts/new', [$contactController, 'New']);
$router->Post('/contacts/create', [$contactController, 'Create']);
$router->Post('/contacts/delete', [$contactController, 'Delete']);

// ----- Profile routes -----
$router->Get('/profile', [$profileController, 'Index']);
$router->Post('/profile/update', [$profileController, 'Update']);
$router->Post('/profile/password', [$profileController, 'ChangePassword']);

// ----- Authentication routes -----
$router->Get('/login', [$authController, 'ShowLogin']);
$router->Post('/login', [$authController, 'Login']);
$router->Get('/logout', [$authController, 'Logout']);

// ----- Language switcher route -----
$router->Get('/language/set', [$languageController, 'Set']);

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