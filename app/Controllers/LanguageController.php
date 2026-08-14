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
use Pulse\Core\RecipientPortalLanguagePreference;
use Pulse\Core\SafeRedirect;
use Pulse\Core\SafetyLanguagePreference;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Core\WebsiteLanguagePreference;
use Pulse\Repositories\UserRepository;
use Pulse\Services\AuthService;

/**
 * @brief Changes the active UI locale.
 */
class LanguageController extends BaseController
{
	/** @var array<int, string> */
	private array $_supportedLocales;
	private UserRepository $_userRepository;

	/** @brief Constructs the controller. @param View $view View. @param Session $session Session. @param AuthService $auth Authentication. @param Logger $logger Logger. @param Request $request Request. @param array<int, string> $supportedLocales Allowed locales. */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		array $supportedLocales,
		UserRepository $userRepository
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_supportedLocales = $supportedLocales;
		$this->_userRepository = $userRepository;
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

		$portalToken = RecipientPortalLanguagePreference::TokenFromRedirect($redirect);

		if ($portalToken !== null)
		{
			$this->_session->Set(RecipientPortalLanguagePreference::SessionKey($portalToken), $locale);
			$this->_logger->Info('Recipient portal language changed', ['locale' => $locale]);
			$this->Redirect($redirect);
		}

		if ($this->IsClosedRecipientPortalRedirect($redirect))
		{
			$this->_logger->Info('Closed recipient portal language changed', ['locale' => $locale]);
			$this->Redirect($this->WithLocaleParameter($redirect, $locale));
		}

		$safetyToken = SafetyLanguagePreference::TokenFromRedirect($redirect);

		if ($safetyToken !== null)
		{
			$this->_session->Set(SafetyLanguagePreference::SessionKey($safetyToken), $locale);
			$this->_logger->Info('Safety page language changed', ['locale' => $locale]);
			$this->Redirect($redirect);
		}

		$this->_session->Set('locale', $locale);
		WebsiteLanguagePreference::Write($locale, $this->_request->IsSecure());
		$currentUser = $this->_auth->GetCurrentUser();

		if (is_array($currentUser))
		{
			$this->_userRepository->UpdateWebsiteLocale((int)$currentUser['id'], $locale);
		}

		$this->_logger->Info('UI language changed', ['locale' => $locale]);
		$this->Flash('success', __('flash.languageswitched', ['locale' => $locale]));
		$this->Redirect($redirect);
	}

	/** @brief Returns whether a safe local redirect targets the token-free recipient closure result page. */
	private function IsClosedRecipientPortalRedirect(string $redirect): bool
	{
		return parse_url($redirect, PHP_URL_PATH) === '/portal/closed';
	}

	/** @brief Replaces the locale query value on a safe local redirect target. */
	private function WithLocaleParameter(string $redirect, string $locale): string
	{
		$path = parse_url($redirect, PHP_URL_PATH);

		if (!is_string($path) || $path === '')
		{
			return '/portal/closed?lang=' . rawurlencode($locale);
		}

		$values = [];
		$query = parse_url($redirect, PHP_URL_QUERY);

		if (is_string($query) && $query !== '')
		{
			parse_str($query, $values);
		}

		$values['lang'] = $locale;
		return $path . '?' . http_build_query($values, '', '&', PHP_QUERY_RFC3986);
	}
}
