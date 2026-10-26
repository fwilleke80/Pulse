<?php

/**
 * @file EmergencyStopController.php
 * @brief Safe confirmation-first emergency stop entry points.
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Services\EmergencyStopService;

/** @brief Coordinates owner dashboard and non-authenticating email emergency links. */
final class EmergencyStopController extends BaseController
{
	private EmergencyStopService $_service;

	/** @brief Constructs the emergency-stop controller. */
	public function __construct(
		\Pulse\Core\View $view,
		\Pulse\Core\Session $session,
		\Pulse\Services\AuthService $auth,
		\Pulse\Core\Logger $logger,
		\Pulse\Core\Request $request,
		EmergencyStopService $service
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_service = $service;
	}

	/** @brief Displays an emergency link without performing any state change. */
	public function Show(): string
	{
		$token = $this->_request->QueryString('token', 128);
		$warning = $this->_service->FindToken($token);
		header('Referrer-Policy: no-referrer');
		header('Cache-Control: no-store, private');
		return $this->_view->Render('emergency.confirm', ['token' => $warning !== null ? $token : '', 'warning' => $warning]);
	}

	/** @brief Executes the one-time emergency email action on explicit POST. */
	public function Confirm(): string
	{
		$token = $this->_request->PostString('token', 128);
		$stopped = $this->_service->StopByToken($token);
		header('Referrer-Policy: no-referrer');
		header('Cache-Control: no-store, private');
		return $this->_view->Render('emergency.result', ['stopped' => $stopped]);
	}

	/** @brief Immediately stops every monitor for an authenticated owner. */
	public function StopDashboard(): void
	{
		$user = $this->RequireUser();
		$this->_service->StopForUser((int)$user['id']);
		$this->Flash('warning', __('emergency.stopped'));
		$this->Redirect('/');
	}

	/** @brief Explicitly restarts previously active monitors after a shutdown. */
	public function Reactivate(): void
	{
		$user = $this->RequireUser();
		$count = $this->_service->ReactivateForUser((int)$user['id']);
		$this->Flash('success', __('emergency.reactivated', ['count' => $count]));
		$this->Redirect('/');
	}
}
