<?php

/**
 * @file MarkdownController.php
 * @brief Authenticated server-side Markdown preview endpoint.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Logger;
use Pulse\Core\MarkdownRenderer;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Services\AuthService;

/**
 * @brief Renders unsaved Markdown with the same renderer used by recipient pages and mail.
 */
final class MarkdownController extends BaseController
{
	private MarkdownRenderer $_renderer;

	/** @brief Constructs the Markdown preview controller. */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		MarkdownRenderer $renderer
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_renderer = $renderer;
	}

	/** @brief Returns safe rendered HTML for the current unsaved editor source. */
	public function Preview(): void
	{
		$this->RequireUser();
		$source = $this->_request->PostString('source', 1000000, false);
		$mode = $this->_request->PostString('mode', 16);
		$html = $mode === 'email'
			? $this->_renderer->ToEmailHtml($source)
			: $this->_renderer->ToHtml($source);

		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store, private');
		echo json_encode(['html' => $html], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	}
}
