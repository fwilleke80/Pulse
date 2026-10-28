<?php
/** Standalone behavioral security regression: php tests/security/privacy_boundaries.php */
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
	if (str_starts_with($class, 'Pulse\\')) {
		require dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 6)) . '.php';
	}
});
function __(string $key): string { return $key; }

class PrivacyDenied extends RuntimeException {}
class PrivacyAuth extends Pulse\Services\AuthService
{
	public function __construct(private array $user) {}
	public function IsAuthenticated(): bool { return true; }
	public function GetCurrentUser(): ?array { return $this->user; }
}
class PrivacyView extends Pulse\Core\View
{
	public function __construct() {}
	public function Render(string $view, array $data = []): string {
		if ($view === 'home.error') { throw new PrivacyDenied(); }
		throw new RuntimeException('Unexpected rendering before authorization');
	}
}
class PrivacyLogger extends Pulse\Core\Logger
{
	public function __construct() {}
	public function Warning(string $message, array $context = []): void {}
}
class PrivacyController extends Pulse\Controllers\BaseController {}

function controller(string $class, array $user): object
{
	$controller = (new ReflectionClass($class))->newInstanceWithoutConstructor();
	foreach (['_auth' => new PrivacyAuth($user), '_view' => new PrivacyView(),
		'_logger' => new PrivacyLogger(), '_request' => new Pulse\Core\Request([], [], [], ['REQUEST_URI' => '/test'])] as $name => $value) {
		(new ReflectionProperty(Pulse\Controllers\BaseController::class, $name))->setValue($controller, $value);
	}
	return $controller;
}
$checks = 0;
function verify(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) { throw new RuntimeException($message); }
}
function denied(object $controller, string $method): void
{
	http_response_code(200);
	try {
		(new ReflectionMethod($controller, $method))->invoke($controller);
		throw new RuntimeException("$method unexpectedly allowed");
	} catch (PrivacyDenied) {
		verify(http_response_code() === 403, "$method must reject with 403");
	}
}

foreach (['user', 'admin', 'administrator', 'invalid', null] as $role) {
	foreach ([null, false, 0, '0', true, 1, '1', 'true', 2, []] as $flag) {
		$user = ['id' => 41, 'role' => $role, 'is_super_user' => $flag];
		$controller = controller(PrivacyController::class, $user);
		foreach (['RequireAdministrator', 'RequireSuperUser'] as $guard) {
			$allowed = $role === 'administrator' && ($guard === 'RequireAdministrator' || in_array($flag, [true, 1, '1'], true));
			if ($allowed) {
				verify((new ReflectionMethod($controller, $guard))->invoke($controller) === $user, "$guard must preserve owner identity");
			} else { denied($controller, $guard); }
		}
	}
}
// No dependency is initialized: rejection must happen before services, uploads or staging are touched.
$maintenance = ['ExportTextDocuments', 'CreateBackup', 'PreflightRestore', 'RestoreBackup',
	'ResumeAfterRestore', 'PreflightUpdate', 'ChangeUpdatePackage', 'InstallUpdate'];
foreach ([['id' => 1, 'role' => 'user', 'is_super_user' => 1],
	['id' => 1, 'role' => 'administrator'], ['id' => 1, 'role' => 'administrator', 'is_super_user' => 0]] as $user) {
	foreach ($maintenance as $action) { denied(controller(Pulse\Controllers\MaintenanceController::class, $user), $action); }
	foreach (['Backups', 'Updates'] as $action) { denied(controller(Pulse\Controllers\AdministrationController::class, $user), $action); }
}
foreach (['Index', 'Configuration', 'Update', 'SendTestNotification', 'RetryFailedNotifications', 'ClearNotificationQueue'] as $action) {
	denied(controller(Pulse\Controllers\AdministrationController::class, ['id' => 1, 'role' => 'user']), $action);
}

// Both preview and download must pass the authenticated ID, ignoring submitted owner IDs and roles.
class PrivacyDocuments extends Pulse\Services\DocumentService
{
	public array $received = [];
	public function __construct() {}
	public function PreparePreviewForUser(int $userId, int $monitorId, int $documentId): array {
		return $this->PrepareDownloadForUser($userId, $monitorId, $documentId);
	}
	public function PrepareDownloadForUser(int $userId, int $monitorId, int $documentId): array {
		$this->received = [$userId, $monitorId, $documentId];
		throw new PrivacyDenied();
	}
}
foreach (['user', 'administrator'] as $role) {
	foreach ([0, 1] as $super) {
		foreach (['Preview', 'Download'] as $action) {
			$c = controller(Pulse\Controllers\DocumentController::class, ['id' => 41, 'role' => $role, 'is_super_user' => $super]);
			$documents = new PrivacyDocuments();
			(new ReflectionProperty($c, '_documentService'))->setValue($c, $documents);
			(new ReflectionProperty(Pulse\Controllers\BaseController::class, '_request'))->setValue($c,
				new Pulse\Core\Request(['monitor_id' => 7, 'document_id' => 8, 'user_id' => 99], [], [], []));
			try { $c->$action(); } catch (PrivacyDenied) {}
			verify($documents->received === [41, 7, 8], "$action used a different owner identity");
		}
	}
}
echo "PASS: $checks behavioral role and controller boundary checks\n";
