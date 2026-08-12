<?php

/**
 * @file index.php
 * @brief Thin front controller: security policy, routes, and dispatch.
 * @author Frank Willeke
 */

declare(strict_types=1);

use Pulse\Controllers\AuthController;
use Pulse\Controllers\ContactController;
use Pulse\Controllers\DocumentController;
use Pulse\Controllers\HomeController;
use Pulse\Controllers\LanguageController;
use Pulse\Controllers\MonitorController;
use Pulse\Controllers\ProfileController;
use Pulse\Controllers\RecipientController;
use Pulse\Controllers\SafetyController;
use Pulse\Core\NotFoundException;
use Pulse\Core\SecurityHeaders;

$container = require dirname(__DIR__) . '/bootstrap.php';

$config = $container['config'];
$db = $container['db'];
$router = $container['router'];
$view = $container['view'];
$request = $container['request'];
$session = $container['session'];
$csrf = $container['csrf'];
$auth = $container['auth'];
$logger = $container['logger'];
$contactRepository = $container['contactRepository'];
$monitorRepository = $container['monitorRepository'];
$recipientRepository = $container['recipientRepository'];
$documentRepository = $container['documentRepository'];
$messageRepository = $container['messageRepository'];
$monitorExecutionService = $container['monitorExecutionService'];
$documentService = $container['documentService'];
$userRepository = $container['userRepository'];
$loginThrottle = $container['loginThrottle'];
$mailQueueRepository = $container['mailQueueRepository'];
$mailQueueWorker = $container['mailQueueWorker'];
$notificationScheduler = $container['notificationScheduler'];
$testNotificationService = $container['testNotificationService'];
$notificationLanguage = $container['notificationLanguage'];
$notificationComposer = $container['notificationComposer'];
$escalationService = $container['escalationService'];

(new SecurityHeaders())->Apply($request, (array)$config['security']);
header('Cache-Control: no-store');

$view->SetGlobals(['flash' => $session->PullFlash()], true);

if ($request->Method() === 'POST' && !$csrf->IsValid($request->PostString('_csrf_token', 128)))
{
	$logger->Warning('Rejected request with invalid CSRF token', ['path' => $request->Path()]);
	http_response_code(419);
	echo $view->Render('home.error', [
		'heading' => __('error.csrf.heading'),
		'message' => __('error.csrf.message'),
	]);
	exit;
}

$homeController = new HomeController(
	$view,
	$session,
	$auth,
	$logger,
	$request,
	$db,
	$config,
	$contactRepository,
	$monitorRepository,
	$monitorExecutionService
);
$authController = new AuthController($view, $session, $auth, $logger, $request, $loginThrottle, $csrf);
$contactController = new ContactController($view, $session, $auth, $logger, $request, $contactRepository, $notificationLanguage);
$languageController = new LanguageController($view, $session, $auth, $logger, $request, (array)$config['available_locales']);
$profileController = new ProfileController(
	$view,
	$session,
	$auth,
	$logger,
	$request,
	$userRepository,
	(int)$config['security']['password_minimum_length'],
	$mailQueueRepository,
	$testNotificationService,
	(bool)$config['mail']['enabled'],
	$notificationLanguage
);
$monitorController = new MonitorController(
	$view,
	$session,
	$auth,
	$logger,
	$request,
	$monitorRepository,
	$contactRepository,
	$documentRepository,
	$messageRepository,
	$documentService,
	$monitorExecutionService,
	$notificationScheduler,
	$mailQueueWorker,
	$escalationService,
	(bool)$config['debug'],
	(bool)$config['mail']['enabled']
);
$documentController = new DocumentController($view, $session, $auth, $logger, $request, $documentService);
$recipientController = new RecipientController(
	$view,
	$session,
	$auth,
	$logger,
	$request,
	$recipientRepository,
	$monitorRepository,
	$documentRepository,
	$notificationComposer
);
$safetyController = new SafetyController(
	$view,
	$session,
	$auth,
	$logger,
	$request,
	$escalationService,
	dirname(__DIR__) . '/app/Lang'
);

$router->Get('/', [$homeController, 'Dashboard']);
$router->Get('/activity', [$homeController, 'Activity']);
$router->Get('/about', [$homeController, 'About']);
$router->Get('/imprint', [$homeController, 'Imprint']);
$router->Get('/health', [$homeController, 'Health']);
$router->Get('/health/readiness', [$homeController, 'Readiness']);

$router->Get('/contacts', [$contactController, 'Index']);
$router->Get('/contacts/new', [$contactController, 'New']);
$router->Get('/contacts/edit', [$contactController, 'Edit']);
$router->Post('/contacts/update', [$contactController, 'Update']);
$router->Post('/contacts/create', [$contactController, 'Create']);
$router->Post('/contacts/delete', [$contactController, 'Delete']);

$router->Get('/profile', [$profileController, 'Index']);
$router->Post('/profile/update', [$profileController, 'Update']);
$router->Post('/profile/password', [$profileController, 'ChangePassword']);
$router->Post('/profile/notifications/test', [$profileController, 'SendTestNotification']);
$router->Post('/profile/notifications/retry', [$profileController, 'RetryFailedNotifications']);

$router->Get('/monitors', [$monitorController, 'Index']);
$router->Get('/monitors/new', [$monitorController, 'New']);
$router->Get('/monitors/edit', [$monitorController, 'Edit']);
$router->Post('/monitors/create', [$monitorController, 'Create']);
$router->Post('/monitors/update', [$monitorController, 'Update']);
$router->Post('/monitors/messages/update', [$monitorController, 'UpdateMessages']);
$router->Post('/monitors/delete', [$monitorController, 'Delete']);
$router->Post('/monitors/check-in', [$monitorController, 'CheckIn']);
$router->Post('/monitors/pause', [$monitorController, 'Pause']);
$router->Post('/monitors/resume', [$monitorController, 'Resume']);
$router->Post('/monitors/force-due', [$monitorController, 'ForceDue']);
$router->Post('/monitors/send-due-notice', [$monitorController, 'SendDueNotice']);
$router->Post('/monitors/send-recipient-notifications', [$monitorController, 'SendRecipientNotifications']);
$router->Get('/monitors/recipients/edit', [$recipientController, 'Edit']);
$router->Post('/monitors/recipients/add', [$recipientController, 'Add']);
$router->Post('/monitors/recipients/update', [$recipientController, 'Update']);
$router->Post('/monitors/recipients/remove', [$recipientController, 'Remove']);

$router->Post('/monitors/documents/upload', [$documentController, 'Upload']);
$router->Post('/monitors/documents/text/create', [$documentController, 'CreateText']);
$router->Post('/monitors/documents/text/update', [$documentController, 'UpdateText']);
$router->Post('/monitors/documents/file/update', [$documentController, 'UpdateFile']);
$router->Post('/monitors/documents/recipients', [$documentController, 'UpdateRecipients']);
$router->Post('/monitors/documents/delete', [$documentController, 'Delete']);
$router->Get('/monitors/documents/download', [$documentController, 'Download']);

$router->Get('/login', [$authController, 'ShowLogin']);
$router->Post('/login', [$authController, 'Login']);
$router->Post('/logout', [$authController, 'Logout']);
$router->Post('/language/set', [$languageController, 'Set']);
$router->Get('/safety/confirm', [$safetyController, 'Show']);
$router->Post('/safety/respond', [$safetyController, 'Respond']);

try
{
	$response = $router->Dispatch($request->Method(), $request->Path());

	if (is_string($response))
	{
		echo $response;
	}
}
catch (NotFoundException)
{
	http_response_code(404);
	echo $view->Render('home.not-found');
}
