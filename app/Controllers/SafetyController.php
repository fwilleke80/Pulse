<?php

/**
 * @file SafetyController.php
 * @brief Public, scanner-safe safety-contact response pages.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Logger;
use Pulse\Core\NotificationLanguage;
use Pulse\Core\Request;
use Pulse\Core\SafetyLanguagePreference;
use Pulse\Core\Session;
use Pulse\Core\Translator;
use Pulse\Core\View;
use Pulse\Services\AuthService;
use Pulse\Services\EscalationService;

/**
 * @brief Makes GET requests read-only and requires an explicit CSRF-protected POST response.
 */
final class SafetyController extends BaseController
{
	private EscalationService $_escalation;
	private NotificationLanguage $_languages;
	private string $_languagePath;

	/** @brief Constructs the public safety controller. */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		EscalationService $escalation,
		NotificationLanguage $languages,
		string $languagePath
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_escalation = $escalation;
		$this->_languages = $languages;
		$this->_languagePath = $languagePath;
	}

	/** @brief Displays a current safety request without changing any state. */
	public function Show(): string
	{
		$token = $this->_request->QueryString('token', 64);
		$request = $this->_escalation->FindSafetyRequestByToken($token);

		if (!is_array($request))
		{
			http_response_code(404);
			return $this->_view->Render('safety.invalid');
		}

		$this->UseRecipientLanguage((string)$request['notification_locale'], $token, $this->_request->QueryString('lang', 10));

		return $this->_view->Render('safety.confirm', [
			'safetyRequest' => $request,
			'token' => $token,
		]);
	}

	/** @brief Records a deliberate confirmation or cannot-confirm response. */
	public function Respond(): string
	{
		$token = $this->_request->PostString('token', 64);
		$request = $this->_escalation->FindSafetyRequestByToken($token);

		if (!is_array($request))
		{
			http_response_code(404);
			return $this->_view->Render('safety.invalid');
		}

		$this->UseRecipientLanguage((string)$request['notification_locale'], $token);
		$decision = $this->_request->PostString('decision', 20);

		if ($decision === 'confirm' && !$this->_request->PostBool('direct_contact'))
		{
			return $this->_view->Render('safety.confirm', [
				'safetyRequest' => $request,
				'token' => $token,
				'validationError' => __('safety.confirm.checkbox_required'),
			]);
		}

		$result = $this->_escalation->RespondToSafetyToken($token, $decision);

		if ($result === 'invalid')
		{
			http_response_code(404);
			return $this->_view->Render('safety.invalid');
		}

		return $this->_view->Render('safety.result', ['result' => $result]);
	}

	/**
	 * @brief Selects the safety-page language, allowing an explicit per-request override.
	 * @param string $storedLocale Language snapshotted when the safety request was created.
	 * @param string $token Raw safety token used only to scope the session override.
	 * @param string $linkLocale Optional locale embedded in the invitation link.
	 */
	private function UseRecipientLanguage(string $storedLocale, string $token, string $linkLocale = ''): void
	{
		$locale = $this->_languages->Resolve($storedLocale);
		$sessionLocale = $this->_session->Get(SafetyLanguagePreference::SessionKey($token));

		if (is_string($sessionLocale) && $this->_languages->IsSupported($sessionLocale))
		{
			$locale = $sessionLocale;
		}
		elseif ($linkLocale !== '' && $this->_languages->IsSupported($linkLocale))
		{
			$locale = $linkLocale;
		}

		setTranslator(new Translator($this->_languagePath, $locale));
		$this->_view->SetGlobals(['locale' => $locale], true);
	}
}
