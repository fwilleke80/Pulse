<?php

/**
 * @file RecipientPortalController.php
 * @brief Public recipient portal invitation and access-code verification endpoints.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Logger;
use Pulse\Core\NotificationLanguage;
use Pulse\Core\RecipientPortalLanguagePreference;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\Translator;
use Pulse\Core\View;
use Pulse\Services\AuthService;
use Pulse\Services\DocumentService;
use Pulse\Services\MailQueueWorker;
use Pulse\Services\RecipientPortalArchiveBuilder;
use Pulse\Services\RecipientPortalService;
use Throwable;

/**
 * @brief Keeps recipient portal GETs read-only and requires a short-lived emailed code before authentication.
 */
final class RecipientPortalController extends BaseController
{
	private RecipientPortalService $_portalService;
	private MailQueueWorker $_mailQueueWorker;
	private NotificationLanguage $_languages;
	private DocumentService $_documentService;
	private RecipientPortalArchiveBuilder $_archiveBuilder;
	private string $_languagePath;

	/** @brief Constructs the public recipient portal controller. */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		RecipientPortalService $portalService,
		MailQueueWorker $mailQueueWorker,
		NotificationLanguage $languages,
		DocumentService $documentService,
		RecipientPortalArchiveBuilder $archiveBuilder,
		string $languagePath
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_portalService = $portalService;
		$this->_mailQueueWorker = $mailQueueWorker;
		$this->_languages = $languages;
		$this->_documentService = $documentService;
		$this->_archiveBuilder = $archiveBuilder;
		$this->_languagePath = $languagePath;
	}

	/** @brief Displays a valid recipient invitation without exposing documents. */
	public function Show(): string
	{
		$token = $this->_request->QueryString('token', 64);
		$delivery = $this->_portalService->FindActiveDelivery($token);

		if (!is_array($delivery))
		{
			http_response_code(404);
			return $this->_view->Render('portal.invalid');
		}

		$this->UseRecipientLanguage((string)$delivery['notification_locale'], $token, $this->_request->QueryString('lang', 10));

		return $this->_view->Render('portal.index', [
			'delivery' => $this->PublicDelivery($delivery),
			'token' => $token,
			'codeSent' => $this->_request->QueryString('notice', 20) === 'sent',
			'isAuthenticatedForDelivery' => $this->_portalService->HasValidSession($this->_session, $token, (int)$delivery['delivery_id']),
		]);
	}

	/** @brief Requests a new 30-minute access code without revealing the configured delivery address. */
	public function RequestCode(): void
	{
		$token = $this->_request->PostString('token', 64);
		$delivery = $this->_portalService->FindActiveDelivery($token);

		if (!is_array($delivery))
		{
			$this->Redirect('/portal?token=' . rawurlencode($token));
		}

		$this->UseRecipientLanguage((string)$delivery['notification_locale'], $token);

		try
		{
			$queueId = $this->_portalService->RequestAccessCode($delivery);

			if (is_int($queueId) && $queueId > 0)
			{
				$this->_mailQueueWorker->ProcessById($queueId);
			}
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Warning('Recipient access-code request could not be delivered', [
				'delivery_id' => (int)$delivery['delivery_id'],
				'exception' => get_class($throwable),
			]);
		}

		// Deliberately identical for sent, rate-limited, and delivery-failure outcomes.
		$this->Redirect('/portal?token=' . rawurlencode($token) . '&notice=sent');
	}

	/** @brief Verifies and consumes an emailed access code. */
	public function VerifyCode(): string
	{
		$token = $this->_request->PostString('token', 64);
		$delivery = $this->_portalService->FindActiveDelivery($token);

		if (!is_array($delivery))
		{
			http_response_code(404);
			return $this->_view->Render('portal.invalid');
		}

		$this->UseRecipientLanguage((string)$delivery['notification_locale'], $token);
		$verified = $this->_portalService->VerifyAccessCode($token, $this->_request->PostString('access_code', 64));

		if (!is_array($verified))
		{
			$this->_view->SetGlobals(['currentTarget' => '/portal?token=' . rawurlencode($token)], true);
			return $this->_view->Render('portal.index', [
				'delivery' => $this->PublicDelivery($delivery),
				'token' => $token,
				'codeSent' => false,
				'isAuthenticatedForDelivery' => false,
				'validationError' => __('portal.code.invalid'),
			]);
		}

		$this->_portalService->GrantSession($this->_session, $token, (int)$verified['delivery_id']);
		$this->Redirect('/portal/access?token=' . rawurlencode($token));
	}

	/** @brief Displays the authenticated recipient portal with the immutable assigned-document snapshot. */
	public function Access(): string
	{
		$token = $this->_request->QueryString('token', 64);
		$delivery = $this->_portalService->FindActiveDelivery($token);

		if (!is_array($delivery))
		{
			http_response_code(404);
			return $this->_view->Render('portal.invalid');
		}

		$this->UseRecipientLanguage((string)$delivery['notification_locale'], $token, $this->_request->QueryString('lang', 10));

		if (!$this->_portalService->HasValidSession($this->_session, $token, (int)$delivery['delivery_id']))
		{
			$this->Redirect('/portal?token=' . rawurlencode($token));
		}

		$documents = $this->_portalService->DocumentsForDelivery((int)$delivery['delivery_id']);
		$totalDownloadBytes = 0;
		$availableDocumentCount = 0;

		foreach ($documents as &$document)
		{
			$isText = (string)($document['storage_type'] ?? '') === 'text';
			$filePath = $isText ? null : $this->_documentService->ResolvePortalSnapshotFile($document);
			$downloadAvailable = $isText || $filePath !== null;
			$sizeBytes = $isText
				? strlen((string)($document['text_content'] ?? ''))
				: max(0, (int)($document['file_size_bytes'] ?? 0));
			$inlineType = $downloadAvailable ? $this->InlineContentType($document) : null;

			$document['download_available'] = $downloadAvailable;
			$document['view_available'] = $inlineType !== null;
			$document['image_preview'] = $inlineType !== null && str_starts_with($inlineType, 'image/');
			$document['size_bytes'] = $sizeBytes;
			$document['type_label'] = $this->DocumentTypeLabel($document);

			if ($downloadAvailable)
			{
				$availableDocumentCount++;
				$totalDownloadBytes += $sizeBytes;
			}
		}
		unset($document);

		return $this->_view->Render('portal.access', [
			'delivery' => $this->AuthenticatedDelivery($delivery),
			'documents' => $documents,
			'token' => $token,
			'availableDocumentCount' => $availableDocumentCount,
			'totalDownloadBytes' => $totalDownloadBytes,
		]);
	}

	/** @brief Streams one document snapshot after checking the recipient session and delivery scope. */
	public function DownloadDocument(): void
	{
		[$token, $delivery] = $this->RequireAuthenticatedDelivery();
		$document = $this->_portalService->DocumentForDelivery(
			(int)$delivery['delivery_id'],
			$this->_request->QueryInt('document')
		);

		if (!is_array($document))
		{
			$this->DocumentNotFound();
		}

		$filename = $this->DocumentDownloadFilename($document);
		$this->PrepareForStreaming();

		if ((string)$document['storage_type'] === 'text')
		{
			$content = (string)($document['text_content'] ?? '');
			$this->SendDownloadHeaders($filename, 'text/plain; charset=utf-8');
			header('Content-Length: ' . strlen($content));
			$this->_portalService->RecordDocumentDownload((int)$delivery['delivery_id'], (int)$document['id']);
			echo $content;
			exit;
		}

		$path = $this->_documentService->ResolvePortalSnapshotFile($document);

		if ($path === null)
		{
			$this->DocumentNotFound();
		}

		$this->SendDownloadHeaders($filename, (string)($document['mime_type'] ?? 'application/octet-stream'));
		$fileSize = filesize($path);

		if (is_int($fileSize))
		{
			header('Content-Length: ' . $fileSize);
		}

		$this->_portalService->RecordDocumentDownload((int)$delivery['delivery_id'], (int)$document['id']);
		readfile($path);
		exit;
	}

	/** @brief Serves a safely inline-viewable document snapshot after checking recipient authorization. */
	public function ViewDocument(): void
	{
		[, $delivery] = $this->RequireAuthenticatedDelivery();
		$document = $this->_portalService->DocumentForDelivery(
			(int)$delivery['delivery_id'],
			$this->_request->QueryInt('document')
		);

		if (!is_array($document))
		{
			$this->DocumentNotFound();
		}

		$contentType = $this->InlineContentType($document);

		if ($contentType === null)
		{
			$this->DocumentNotFound();
		}

		$filename = $this->DocumentDownloadFilename($document);
		$this->PrepareForStreaming();

		if ((string)$document['storage_type'] === 'text')
		{
			$content = (string)($document['text_content'] ?? '');
			$this->SendInlineHeaders($filename, $contentType);
			header('Content-Length: ' . strlen($content));
			echo $content;
			exit;
		}

		$path = $this->_documentService->ResolvePortalSnapshotFile($document);

		if ($path === null)
		{
			$this->DocumentNotFound();
		}

		$this->SendInlineHeaders($filename, $contentType);
		$fileSize = filesize($path);

		if (is_int($fileSize))
		{
			header('Content-Length: ' . $fileSize);
		}

		readfile($path);
		exit;
	}

	/** @brief Streams every available delivery document as one portable ZIP/ZIP64 archive. */
	public function DownloadAll(): void
	{
		[, $delivery] = $this->RequireAuthenticatedDelivery();
		$documents = $this->_portalService->DocumentsForDelivery((int)$delivery['delivery_id']);
		$available = array_values(array_filter(
			$documents,
			fn (array $document): bool => (string)($document['storage_type'] ?? '') === 'text'
				|| $this->_documentService->ResolvePortalSnapshotFile($document) !== null
		));

		if ($available === [])
		{
			$this->DocumentNotFound();
		}

		$filename = $this->SafeFilename((string)$delivery['owner_name']) . '-documents.zip';
		$this->PrepareForStreaming();
		$this->SendDownloadHeaders($filename, 'application/zip');
		header('X-Accel-Buffering: no');

		$output = fopen('php://output', 'wb');

		if ($output === false)
		{
			exit;
		}

		try
		{
			$writtenCount = $this->_archiveBuilder->Stream($available, $output);
			$this->_portalService->RecordDownloadAll((int)$delivery['delivery_id'], $writtenCount);
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Error('Recipient download-all stream failed', [
				'delivery_id' => (int)$delivery['delivery_id'],
				'exception' => get_class($throwable),
			]);
		}
		finally
		{
			fclose($output);
		}

		exit;
	}

	/** @return array{0: string, 1: array<string, mixed>} @brief Requires an active token and matching authenticated recipient session. */
	private function RequireAuthenticatedDelivery(): array
	{
		$token = $this->_request->QueryString('token', 64);
		$delivery = $this->_portalService->FindActiveDelivery($token);

		if (!is_array($delivery))
		{
			$this->DocumentNotFound();
		}

		$this->UseRecipientLanguage((string)$delivery['notification_locale'], $token);

		if (!$this->_portalService->HasValidSession($this->_session, $token, (int)$delivery['delivery_id']))
		{
			$this->Redirect('/portal?token=' . rawurlencode($token));
		}

		return [$token, $delivery];
	}

	/** @return array<string, mixed> @brief Returns recipient-authenticated metadata including the released message snapshot. */
	private function AuthenticatedDelivery(array $delivery): array
	{
		$public = $this->PublicDelivery($delivery);
		$public['recipient_name'] = (string)($delivery['recipient_name'] ?? '');
		$public['message_subject'] = (string)($delivery['subject'] ?? '');
		$public['message_body'] = trim(str_replace(
			'[Recipient portal link redacted]',
			__('portal.access.message_portal_reference'),
			(string)($delivery['body_text'] ?? '')
		));
		return $public;
	}

	/** @brief Builds a recipient-facing download filename while preserving a file extension where possible. */
	private function DocumentDownloadFilename(array $document): string
	{
		$title = $this->SafeFilename((string)($document['title'] ?? 'document'));

		if ((string)($document['storage_type'] ?? '') === 'text')
		{
			return str_ends_with(strtolower($title), '.txt') ? $title : $title . '.txt';
		}

		$extension = pathinfo((string)($document['original_filename'] ?? ''), PATHINFO_EXTENSION);

		if ($extension !== '' && pathinfo($title, PATHINFO_EXTENSION) === '')
		{
			$title .= '.' . $extension;
		}

		return $title;
	}

	/** @brief Normalizes a recipient-facing attachment filename without changing the stored file. */
	private function SafeFilename(string $filename): string
	{
		$filename = preg_replace('~[\\/\x00-\x1F\x7F]+~u', '-', trim($filename)) ?? '';
		$filename = trim($filename, " .\t\n\r\0\x0B-");
		return $filename !== '' ? $filename : 'document';
	}

	/** @brief Releases the PHP session lock and output buffers before a potentially long recipient stream. */
	private function PrepareForStreaming(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE)
		{
			session_write_close();
		}

		@set_time_limit(0);

		while (ob_get_level() > 0)
		{
			ob_end_clean();
		}
	}

	/** @brief Returns a safe inline content type, or null for download-only formats. */
	private function InlineContentType(array $document): ?string
	{
		if ((string)($document['storage_type'] ?? '') === 'text')
		{
			return 'text/plain; charset=utf-8';
		}

		$mimeType = strtolower(trim((string)($document['mime_type'] ?? '')));
		$allowed = [
			'application/pdf',
			'image/gif',
			'image/jpeg',
			'image/png',
			'image/webp',
			'image/avif',
			'text/plain',
		];

		return in_array($mimeType, $allowed, true) ? $mimeType : null;
	}

	/** @brief Returns a compact recipient-facing file-type label. */
	private function DocumentTypeLabel(array $document): string
	{
		if ((string)($document['storage_type'] ?? '') === 'text')
		{
			return 'TXT';
		}

		$extension = strtoupper(pathinfo((string)($document['original_filename'] ?? ''), PATHINFO_EXTENSION));

		if ($extension !== '')
		{
			return substr($extension, 0, 8);
		}

		$mimeType = strtolower((string)($document['mime_type'] ?? ''));
		return match ($mimeType)
		{
			'application/pdf' => 'PDF',
			'image/jpeg' => 'JPG',
			'image/png' => 'PNG',
			default => __('portal.documents.type.file'),
		};
	}

	/** @brief Emits non-cacheable inline-view headers for passive content types only. */
	private function SendInlineHeaders(string $filename, string $contentType): void
	{
		$asciiFilename = preg_replace('/[^A-Za-z0-9._ -]/', '_', $filename) ?: 'document';
		header('Content-Type: ' . $contentType);
		header('Content-Disposition: inline; filename="' . str_replace('"', '', $asciiFilename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
		header('Cache-Control: no-store, private');
		header('Pragma: no-cache');
		header('X-Content-Type-Options: nosniff');
	}

	/** @brief Emits non-cacheable attachment headers. */
	private function SendDownloadHeaders(string $filename, string $contentType): void
	{
		$asciiFilename = preg_replace('/[^A-Za-z0-9._ -]/', '_', $filename) ?: 'document';
		header('Content-Type: ' . $contentType);
		header('Content-Disposition: attachment; filename="' . str_replace('"', '', $asciiFilename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
		header('Cache-Control: no-store, private');
		header('Pragma: no-cache');
		header('X-Content-Type-Options: nosniff');
	}

	/** @brief Returns a generic 404 without revealing whether a snapshot or stored file once existed. */
	private function DocumentNotFound(): never
	{
		http_response_code(404);
		header('Content-Type: text/plain; charset=utf-8');
		echo __('portal.document.not_found');
		exit;
	}

	/**
	 * @brief Returns only the delivery metadata that public portal templates are allowed to see.
	 * @return array<string, mixed> Public, address-free view model.
	 */
	private function PublicDelivery(array $delivery): array
	{
		return [
			'delivery_id' => (int)$delivery['delivery_id'],
			'owner_name' => (string)$delivery['owner_name'],
			'monitor_name' => (string)$delivery['monitor_name'],
			'portal_expires_at' => $delivery['portal_expires_at'] ?? null,
		];
	}

	/** @brief Selects portal language with a per-invitation session override. */
	private function UseRecipientLanguage(string $storedLocale, string $token, string $linkLocale = ''): void
	{
		$locale = $this->_languages->Resolve($storedLocale);
		$sessionLocale = $this->_session->Get(RecipientPortalLanguagePreference::SessionKey($token));

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
