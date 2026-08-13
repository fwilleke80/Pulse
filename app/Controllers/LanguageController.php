<?php

/**
 * @file LanguageController.php
 * @brief CSRF-protected language switching with local redirects only.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Logger;
use Pulse\Core\Request;
use Pulse\Core\SafeRedirect;
use Pulse\Core\SafetyLanguagePreference;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Services\AuthService;

/**
 * @brief Changes the active UI locale.
 */
class LanguageController extends BaseController
{
	/** @var array<int, string> */
	private array $_supportedLocales;

	/** @brief Constructs the controller. @param View $view View. @param Session $session Session. @param AuthService $auth Authentication. @param Logger $logger Logger. @param Request $request Request. @param array<int, string> $supportedLocales Allowed locales. */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		array $supportedLocales
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_supportedLocales = $supportedLocales;
	}

	/** @brief Changes the active locale stored in the session. */
	public function Set(): void
	{
		$locale = $this->_request->PostString('locale', 10);
		$redirect = SafeRedirect::Normalize($this->_request->PostString('redirect', 2048), '/');

		if (!in_array($locale, $this->_supportedLocales, true))
		{
			$this->Flash('error', __('flash.language_unsupported'));
			$this->Redirect($redirect);
		}

		$safetyToken = SafetyLanguagePreference::TokenFromRedirect($redirect);

		if ($safetyToken !== null)
		{
			$this->_session->Set(SafetyLanguagePreference::SessionKey($safetyToken), $locale);
			$this->_logger->Info('Safety page language changed', ['locale' => $locale]);
			$this->Redirect($redirect);
		}

		$this->_session->Set('locale', $locale);
		$this->_logger->Info('UI language changed', ['locale' => $locale]);
		$this->Flash('success', __('flash.languageswitched', ['locale' => $locale]));
		$this->Redirect($redirect);
	}
}
