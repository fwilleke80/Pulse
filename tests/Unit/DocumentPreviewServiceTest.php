<?php

/**
 * @file DocumentPreviewServiceTest.php
 * @brief Unit checks for safe inline document classification and bounded text views.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Services\DocumentPreviewService;

final class DocumentPreviewServiceTest extends TestCase
{
	private DocumentPreviewService $_service;

	protected function setUp(): void
	{
		$this->_service = new DocumentPreviewService();
	}

	/** @brief Classifies browser-native passive formats without admitting active content. */
	public function testClassifiesSafeBrowserFormats(): void
	{
		self::assertSame(DocumentPreviewService::KIND_PDF, $this->_service->Kind($this->File('guide.pdf', 'application/pdf')));
		self::assertSame(DocumentPreviewService::KIND_IMAGE, $this->_service->Kind($this->File('photo.webp', 'image/webp')));
		self::assertSame(DocumentPreviewService::KIND_AUDIO, $this->_service->Kind($this->File('message.mp3', 'audio/mpeg')));
		self::assertSame(DocumentPreviewService::KIND_VIDEO, $this->_service->Kind($this->File('journey.mp4', 'video/mp4')));
		self::assertSame(DocumentPreviewService::KIND_MARKDOWN, $this->_service->Kind($this->File('note.md', 'text/plain')));
		self::assertSame(DocumentPreviewService::KIND_CSV, $this->_service->Kind($this->File('route.csv', 'text/csv')));
		self::assertSame(DocumentPreviewService::KIND_JSON, $this->_service->Kind($this->File('data.json', 'application/json')));
		self::assertSame(DocumentPreviewService::KIND_DOWNLOAD, $this->_service->Kind($this->File('page.html', 'text/html')));
		self::assertSame(DocumentPreviewService::KIND_DOWNLOAD, $this->_service->Kind($this->File('drawing.svg', 'image/svg+xml')));
	}

	/** @brief Keeps Pulse-created text as rendered Markdown without requiring a stored file. */
	public function testBuildsNativeTextFrame(): void
	{
		$document = [
			'storage_type' => 'text',
			'text_content' => "# Hello\n\nPrivate message.",
		];

		$preview = $this->_service->BuildTextFrame($document, null);

		self::assertIsArray($preview);
		self::assertSame(DocumentPreviewService::KIND_MARKDOWN, $preview['kind']);
		self::assertSame($document['text_content'], $preview['content']);
		self::assertFalse($preview['truncated']);
	}

	/** @return array<string, string> @brief Creates the stored metadata used by classification checks. */
	private function File(string $filename, string $mimeType): array
	{
		return [
			'storage_type' => 'file',
			'original_filename' => $filename,
			'mime_type' => $mimeType,
		];
	}
}
