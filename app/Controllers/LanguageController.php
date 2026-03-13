<?php
/**
 * @file LanguageController.php
 * @brief Controller for language switching.
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Session;
use Pulse\Core\Translator;
use Pulse\Core\View;
use Pulse\Services\AuthService;

/**
 * @brief Controller for language switching.
 */
class LanguageController extends BaseController
{
	private Translator $_translator;

	/** @var string[] */
	private array $_supportedLocales;

	/**
	 * @brief Constructs the language controller.
	 * @param View $view View renderer.
	 * @param Session $session Session service.
	 * @param AuthService $auth Authentication service.
	 * @param Translator $translator Translator service.
	 * @param string[] $supportedLocales List of allowed locales.
	 */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Translator $translator,
		array $supportedLocales
	)
	{
		parent::__construct($view, $session, $auth);
		$this->_translator = $translator;
		$this->_supportedLocales = $supportedLocales;
	}

	/**
	 * @brief Changes the active locale stored in the session.
	 */
	public function Set(): void
	{
		$locale = trim((string)($_GET['locale'] ?? ''));

		if ($locale === '' || !in_array($locale, $this->_supportedLocales, true))
		{
			$this->Flash('error', 'Unsupported language.');
			$this->Redirect($this->GetRedirectTarget());
		}

		$this->_session->Set('locale', $locale);
		$this->Flash('success', e__('flash.languageswitched', ['locale' => $locale]));
		$this->Redirect($this->GetRedirectTarget());
	}

	/**
	 * @brief Returns the path to redirect back to.
	 * @return string
	 */
	private function GetRedirectTarget(): string
	{
		return trim((string)($_GET['redirect'] ?? ((string)($_SERVER['HTTP_REFERER'] ?? '/'))));
	}
}