<?php

/**
 * @file SafetyController.php
 * @brief Public, scanner-safe safety-contact response pages.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Logger;
use Pulse\Core\Request;
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
	private string $_languagePath;

	/** @brief Constructs the public safety controller. */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		EscalationService $escalation,
		string $languagePath
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_escalation = $escalation;
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

		$this->UseRecipientLanguage((string)$request['notification_locale']);

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

		$this->UseRecipientLanguage((string)$request['notification_locale']);
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

	/** @brief Selects the safety contact's stored language independently of the UI session. */
	private function UseRecipientLanguage(string $locale): void
	{
		$locale = in_array($locale, ['en', 'de'], true) ? $locale : 'en';
		setTranslator(new Translator($this->_languagePath, $locale));
		$this->_view->SetGlobals(['locale' => $locale], true);
	}
}
