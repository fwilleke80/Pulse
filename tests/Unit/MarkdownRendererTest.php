<?php

/**
 * @file MarkdownRendererTest.php
 * @brief Tests safe Markdown rendering for portal and mail content.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\MarkdownRenderer;

final class MarkdownRendererTest extends TestCase
{
	public function testRendersSupportedMarkdownWithoutRawHtml(): void
	{
		$renderer = new MarkdownRenderer();
		$html = $renderer->ToHtml("## Heading\n\nThis is **important** and [safe](https://example.com).\n\n<script>alert(1)</script>");

		self::assertStringContainsString('<h2>Heading</h2>', $html);
		self::assertStringContainsString('<strong>important</strong>', $html);
		self::assertStringContainsString('href="https://example.com"', $html);
		self::assertStringNotContainsString('<script>', $html);
		self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
	}

	public function testRejectsUnsafeLinkSchemes(): void
	{
		$renderer = new MarkdownRenderer();
		$html = $renderer->ToHtml('[click](javascript:alert(1))');

		self::assertStringNotContainsString('javascript:', $html);
		self::assertStringContainsString('click', $html);
	}

	public function testProducesReadablePlainTextAlternative(): void
	{
		$renderer = new MarkdownRenderer();
		$text = $renderer->ToPlainText("# Heading\n\nRead **this** at [Pulse](https://example.com).\n\n- One\n- Two");

		self::assertStringContainsString('Heading', $text);
		self::assertStringContainsString('Read this at Pulse (https://example.com).', $text);
		self::assertStringContainsString('- One', $text);
		self::assertStringNotContainsString('**', $text);
	}

	public function testEmailRendererUsesInlineStylesOnly(): void
	{
		$renderer = new MarkdownRenderer();
		$html = $renderer->ToEmailHtml("## Heading\n\n**Important**");

		self::assertStringContainsString('style="', $html);
		self::assertStringContainsString('<h2 style="', $html);
		self::assertStringNotContainsString('<style', $html);
		self::assertStringNotContainsString('<script', $html);
	}
	/** @brief Ensures CommonMark-style two-space hard line breaks are preserved. */
	public function testTwoTrailingSpacesForceHardLineBreak(): void
	{
		$renderer = new MarkdownRenderer();
		$html = $renderer->ToHtml("First line  \nSecond line\nThird line");

		self::assertStringContainsString('First line<br>Second line Third line', $html);
	}

}
