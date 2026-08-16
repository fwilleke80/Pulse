<?php

/**
 * @file index.php
 * @brief Thin front controller: security policy, routes, and dispatch.
 * @author Frank Willeke
 */

declare(strict_types=1);

$installerPath = __DIR__ . '/install.php';

if (is_file($installerPath))
{
	$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
	$scriptDirectory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
	$installerUrl = ($scriptDirectory === '' || $scriptDirectory === '.')
		? '/install.php'
		: $scriptDirectory . '/install.php';
	header('Cache-Control: no-store');
	header('Location: ' . $installerUrl);
	exit;
}

use Pulse\Controllers\AdministrationController;
use Pulse\Controllers\AuthController;
use Pulse\Controllers\ContactController;
use Pulse\Controllers\DocumentController;
use Pulse\Controllers\HomeController;
use Pulse\Controllers\LanguageController;
use Pulse\Controllers\MarkdownController;
use Pulse\Controllers\MonitorController;
use Pulse\Controllers\ProfileController;
use Pulse\Controllers\RecipientController;
use Pulse\Controllers\RecipientPortalController;
use Pulse\Controllers\SafetyController;
use Pulse\Controllers\SecurityController;
use Pulse\Controllers\QuickCheckInController;
use Pulse\Core\NotFoundException;
use Pulse\Core\SecurityHeaders;

$container = require dirname(__DIR__) . '/bootstrap.php';

$config = $container['config'];
$dbConfig = $container['dbConfig'];
$environmentFile = $container['environmentFile'];
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
$recipientPortalService = $container['recipientPortalService'];
$recipientPortalArchiveBuilder = $container['recipientPortalArchiveBuilder'];
$markdownRenderer = $container['markdownRenderer'];
$securityCredentialRepository = $container['securityCredentialRepository'];
$passkeyService = $container['passkeyService'];
$quickCheckInService = $container['quickCheckInService'];

(new SecurityHeaders())->Apply($request, (array)$config['security']);
header('Cache-Control: no-store');

$view->SetGlobals(['flash' => $session->PullFlash()], true);

if ($request->Method() === 'POST' && !$csrf->IsValid($request->PostString('_csrf_token', 128)))
{
	if ($request->Path() === '/login' && $auth->IsAuthenticated())
	{
		$logger->Info('Ignored stale login POST after authentication');
		$quickCheckInResult = $session->Get('pulse_quick_checkin_result');
		$destination = is_array($quickCheckInResult) ? '/quick-check-in/success' : '/';
		header('Location: ' . $destination, true, 303);
		exit;
	}

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
$authController = new AuthController($view, $session, $auth, $logger, $request, $loginThrottle, $csrf, $quickCheckInService, $monitorExecutionService);

$securityController = new SecurityController(
	$view,
	$session,
	$auth,
	$logger,
	$request,
	$securityCredentialRepository,
	$passkeyService,
	$csrf,
	$quickCheckInService,
	$monitorExecutionService
);
$quickCheckInController = new QuickCheckInController(
	$view,
	$session,
	$auth,
	$logger,
	$request,
	$quickCheckInService,
	$passkeyService,
	$monitorExecutionService
);
$contactController = new ContactController($view, $session, $auth, $logger, $request, $contactRepository, $notificationLanguage);
$languageController = new LanguageController($view, $session, $auth, $logger, $request, (array)$config['available_locales'], $userRepository);
$profileController = new ProfileController(
	$view,
	$session,
	$auth,
	$logger,
	$request,
	$userRepository,
	$securityCredentialRepository,
	(int)$config['security']['password_minimum_length'],
	$notificationLanguage
);
$administrationController = new AdministrationController(
	$view,
	$session,
	$auth,
	$logger,
	$request,
	$environmentFile,
	$mailQueueRepository,
	$container['systemStatusRepository'],
	$testNotificationService,
	(bool)$config['debug'],
	(bool)$config['mail']['enabled'],
	(array)$config['available_locales'],
	$dbConfig
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
	$notificationComposer,
	(array)$config['available_locales'],
	(bool)$config['debug'],
	(bool)$config['mail']['enabled']
);
$documentController = new DocumentController($view, $session, $auth, $logger, $request, $documentService);
$markdownController = new MarkdownController($view, $session, $auth, $logger, $request, $markdownRenderer);
$recipientController = new RecipientController(
	$view,
	$session,
	$auth,
	$logger,
	$request,
	$recipientRepository,
	$monitorRepository,
	$documentRepository,
	$notificationComposer,
	$recipientPortalService
);
$safetyController = new SafetyController(
	$view,
	$session,
	$auth,
	$logger,
	$request,
	$escalationService,
	$notificationLanguage,
	dirname(__DIR__) . '/app/Lang'
);
$recipientPortalController = new RecipientPortalController(
	$view,
	$session,
	$auth,
	$logger,
	$request,
	$recipientPortalService,
	$mailQueueWorker,
	$notificationLanguage,
	$documentService,
	$recipientPortalArchiveBuilder,
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

$router->Get('/administration', [$administrationController, 'Index']);
$router->Post('/administration/update', [$administrationController, 'Update']);
$router->Post('/administration/mail/test', [$administrationController, 'SendTestNotification']);
$router->Post('/administration/mail/retry', [$administrationController, 'RetryFailedNotifications']);
$router->Post('/administration/mail/clear', [$administrationController, 'ClearNotificationQueue']);

$router->Post('/markdown/preview', [$markdownController, 'Preview']);

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
$router->Post('/monitors/reset-reactivate', [$monitorController, 'ResetAndReactivate']);
$router->Post('/monitors/archive', [$monitorController, 'Archive']);
$router->Post('/monitors/force-due', [$monitorController, 'ForceDue']);
$router->Post('/monitors/send-due-notice', [$monitorController, 'SendDueNotice']);
$router->Post('/monitors/send-safety-contact-notifications', [$monitorController, 'SendSafetyContactNotifications']);
$router->Post('/monitors/expire-safety-contact-window', [$monitorController, 'ExpireSafetyContactWindow']);
$router->Post('/monitors/send-recipient-notifications', [$monitorController, 'SendRecipientNotifications']);
$router->Get('/monitors/recipients/edit', [$recipientController, 'Edit']);
$router->Post('/monitors/recipients/add', [$recipientController, 'Add']);
$router->Post('/monitors/recipients/update', [$recipientController, 'Update']);
$router->Post('/monitors/recipients/portal/revoke', [$recipientController, 'RevokePortal']);
$router->Post('/monitors/recipients/delivery/portal/update', [$recipientController, 'UpdateReleasedPortal']);
$router->Post('/monitors/recipients/delivery/document/update', [$recipientController, 'UpdateReleasedDocument']);
$router->Post('/monitors/recipients/remove', [$recipientController, 'Remove']);

$router->Post('/monitors/documents/upload', [$documentController, 'Upload']);
$router->Post('/monitors/documents/text/create', [$documentController, 'CreateText']);
$router->Post('/monitors/documents/text/update', [$documentController, 'UpdateText']);
$router->Post('/monitors/documents/file/update', [$documentController, 'UpdateFile']);
$router->Post('/monitors/documents/recipients', [$documentController, 'UpdateRecipients']);
$router->Post('/monitors/documents/delete', [$documentController, 'Delete']);
$router->Get('/monitors/documents/download', [$documentController, 'Download']);


$router->Post('/security/passkeys/register/options', [$securityController, 'RegisterOptions']);
$router->Post('/security/passkeys/register/verify', [$securityController, 'RegisterVerify']);
$router->Post('/security/passkeys/delete', [$securityController, 'DeletePasskey']);
$router->Post('/login/passkey/options', [$securityController, 'LoginOptions']);
$router->Post('/login/passkey/verify', [$securityController, 'LoginVerify']);
$router->Get('/quick-check-in', [$quickCheckInController, 'Open']);
$router->Post('/quick-check-in/passkey/options', [$quickCheckInController, 'PasskeyOptions']);
$router->Post('/quick-check-in/passkey/verify', [$quickCheckInController, 'PasskeyVerify']);
$router->Get('/quick-check-in/success', [$quickCheckInController, 'Success']);

$router->Get('/login', [$authController, 'ShowLogin']);
$router->Post('/login', [$authController, 'Login']);
$router->Post('/logout', [$authController, 'Logout']);
$router->Post('/language/set', [$languageController, 'Set']);
$router->Get('/safety/confirm', [$safetyController, 'Show']);
$router->Post('/safety/respond', [$safetyController, 'Respond']);
$router->Get('/portal', [$recipientPortalController, 'Show']);
$router->Post('/portal/code/request', [$recipientPortalController, 'RequestCode']);
$router->Post('/portal/code/verify', [$recipientPortalController, 'VerifyCode']);
$router->Get('/portal/access', [$recipientPortalController, 'Access']);
$router->Get('/portal/closed', [$recipientPortalController, 'Closed']);
$router->Get('/portal/close', [$recipientPortalController, 'CloseConfirmation']);
$router->Post('/portal/close', [$recipientPortalController, 'ClosePermanently']);
$router->Get('/portal/document/view', [$recipientPortalController, 'ViewDocument']);
$router->Get('/portal/document/download', [$recipientPortalController, 'DownloadDocument']);
$router->Get('/portal/documents/download-all', [$recipientPortalController, 'DownloadAll']);

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
