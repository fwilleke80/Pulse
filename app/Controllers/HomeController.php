<?php

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Database;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Core\Logger;
use Pulse\Core\Request;
use Pulse\Services\AuthService;
use Pulse\Services\MonitorExecutionService;

/**
 * @brief Controller for home and utility routes.
 */
class HomeController extends BaseController
{
	private Database $_db;

	/** @var array<string, mixed> */
	private array $_config;

	private \Pulse\Repositories\ContactRepository $_contactRepository;
	private \Pulse\Repositories\MonitorRepository $_monitorRepository;
	private MonitorExecutionService $_monitorExecutionService;

	/**
	 * @brief Constructs the home controller.
	 * @param \Pulse\Core\View $view View renderer.
	 * @param \Pulse\Core\Session $session Session service.
	 * @param \Pulse\Services\AuthService $auth Authentication service.
	 * @param \Pulse\Core\Logger $logger Application logger.
	 * @param Request $request Current request.
	 * @param Database $db Database service.
	 * @param array<string, mixed> $config Application configuration.
	 * @param \Pulse\Repositories\ContactRepository $contactRepository Contact repository.
	 * @param \Pulse\Repositories\MonitorRepository $monitorRepository Monitor repository.
	 * @param MonitorExecutionService $monitorExecutionService Check-in lifecycle service.
	 */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		Database $db,
		array $config,
		\Pulse\Repositories\ContactRepository $contactRepository,
		\Pulse\Repositories\MonitorRepository $monitorRepository,
		MonitorExecutionService $monitorExecutionService
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_db = $db;
		$this->_config = $config;
		$this->_contactRepository = $contactRepository;
		$this->_monitorRepository = $monitorRepository;
		$this->_monitorExecutionService = $monitorExecutionService;
	}

	/**
	 * @brief Displays the dashboard or redirects to login.
	 * @return string
	 */
	public function Dashboard(): string
	{
		$user = $this->RequireUser();
		$this->_monitorExecutionService->SynchronizeDueCyclesForUser((int)$user['id']);
		$contactCount = $this->_contactRepository->CountByUserId((int)$user['id']);
		$monitorCount = $this->_monitorRepository->CountByUserId((int)$user['id']);
		$monitors = $this->_monitorRepository->FindAllByUserId((int)$user['id']);

		$this->_logger->Info('User ID ' . $user['id'] . ' accessed dashboard');

		return $this->_view->Render('home.dashboard', [
			'user' => $user,
			'contactCount' => $contactCount,
			'monitorCount' => $monitorCount,
			'monitors' => $monitors,
			'recentActivity' => $this->_monitorExecutionService->FindRecentActivityForUser((int)$user['id'], 5),
			'mailEnabled' => (bool)($this->_config['mail']['enabled'] ?? false),
			'debugEnabled' => (bool)($this->_config['debug'] ?? false),
		]);
	}

	/** @brief Displays the authenticated owner's paginated lifecycle history. */
	public function Activity(): string
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];
		$perPage = 50;
		$total = $this->_monitorExecutionService->CountActivityForUser($userId);
		$totalPages = max(1, (int)ceil($total / $perPage));
		$page = max(1, min($totalPages, $this->_request->QueryInt('page', 1)));

		return $this->_view->Render('home.activity', [
			'activity' => $this->_monitorExecutionService->FindActivityPageForUser($userId, $page, $perPage),
			'page' => $page,
			'totalPages' => $totalPages,
			'total' => $total,
		]);
	}

	/**
	 * @brief Displays the about page.
	 * @return string
	 */
	public function About(): string
	{
		return $this->_view->Render('static.about');
	}

	/**
	 * @brief Displays the imprint page.
	 * @return string
	 */
	public function Imprint(): string
	{
		return $this->_view->Render('static.imprint');
	}

	/**
	 * @brief Returns the application health status as JSON.
	 */
	public function Health(): void
	{
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode(['status' => 'ok'], JSON_UNESCAPED_SLASHES);
	}

	/**
	 * @brief Returns authenticated operational readiness details.
	 */
	public function Readiness(): void
	{
		$this->RequireAuth();
		$databaseOk = $this->_db->CanConnect();
		$configOk = isset($this->_config['name'], $this->_config['timezone']);

		$storageDirs = [
			'storage' => dirname(__DIR__, 2) . '/storage',
			'logs' => dirname(__DIR__, 2) . '/storage/logs',
			'uploads' => dirname(__DIR__, 2) . '/storage/uploads',
			'tmp' => dirname(__DIR__, 2) . '/storage/tmp',
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
				'time' => gmdate('c'),
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
	}
}
