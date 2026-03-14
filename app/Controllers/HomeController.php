<?php

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Database;

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

	/**
	 * @brief Constructs the home controller.
	 * @param \Pulse\Core\View $view View renderer.
	 * @param \Pulse\Core\Session $session Session service.
	 * @param \Pulse\Services\AuthService $auth Authentication service.
	 * @param Database $db Database service.
	 * @param array<string, mixed> $config Application configuration.
	 */
	public function __construct(
		\Pulse\Core\View $view,
		\Pulse\Core\Session $session,
		\Pulse\Services\AuthService $auth,
		\Pulse\Core\Logger $logger,
		Database $db,
		array $config,
		\Pulse\Repositories\ContactRepository $contactRepository,
		\Pulse\Repositories\MonitorRepository $monitorRepository
	)
	{
		parent::__construct($view, $session, $auth, $logger);
		$this->_db = $db;
		$this->_config = $config;
		$this->_contactRepository = $contactRepository;
		$this->_monitorRepository = $monitorRepository;
	}

	/**
	 * @brief Displays the dashboard or redirects to login.
	 * @return string
	 */
	public function Dashboard(): string
	{
		$user = $this->RequireUser();
		$contactCount = $this->_contactRepository->CountByUserId((int)$user['id']);
		$monitorCount = $this->_monitorRepository->CountByUserId((int)$user['id']);

		$this->_logger->Info('User ID ' . $user['id'] . ' accessed dashboard');

		return $this->_view->Render('home.dashboard', [
			'user' => $user,
			'contactCount' => $contactCount,
			'monitorCount' => $monitorCount,
		]);
	}

	/**
	 * @brief Displays the imprint page.
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
		$databaseOk = $this->_db->CanConnect();

		$configOk =
			isset($this->_config['name']) &&
			isset($this->_config['timezone']);

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

		$this->_logger->Info('Health check performed', [
			'database' => $databaseOk ? 'ok' : 'error',
			'config' => $configOk ? 'ok' : 'error',
			'directories' => $directories,
		]);

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
	}
}