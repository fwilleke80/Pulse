<?php

declare(strict_types=1);

use Pulse\Core\Database;
use Pulse\Core\Router;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Repositories\UserRepository;
use Pulse\Services\AuthService;

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

$appConfig = require __DIR__ . '/config/app.php';
$dbConfig = require __DIR__ . '/config/database.php';

date_default_timezone_set($appConfig['timezone']);

$database = new Database($dbConfig);
$router = new Router();
$view = new View(__DIR__ . '/app/Views');
$session = new Session();
$userRepository = new UserRepository($database);
$auth = new AuthService($userRepository, $session);

$session->Start();

return [
	'config' => $appConfig,
	'db' => $database,
	'router' => $router,
	'view' => $view,
	'session' => $session,
	'userRepository' => $userRepository,
	'auth' => $auth,
];