<?php

/**
 * @file DocumentExcerptService.php
 * @brief Readable plain-text excerpts of rendered Markdown documents.
 */

declare(strict_types=1);

namespace Pulse\Services;

use Pulse\Core\MarkdownRenderer;

/** @brief Produces a short recipient-card excerpt without repeating the full document. */
final class DocumentExcerptService
{
	/**
	 * @brief Extracts visible text from Pulse's rendered Markdown and truncates at a word boundary.
	 * @param string $source Original Markdown content.
	 * @param int $limit Maximum approximate number of Unicode characters.
	 * @return string Plain text; the calling view must HTML-escape the result.
	 */
	public static function FromMarkdown(string $source, int $limit = 200): string
	{
		$renderer = new MarkdownRenderer();
		$html = $renderer->ToHtml($source);
		$html = preg_replace('~<br\s*/?>|</(?:p|h[1-6]|li|pre|blockquote)>~i', ' ', $html) ?? $html;
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
		$characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

		if (!is_array($characters) || count($characters) <= $limit)
		{
			return $text;
		}

		$excerpt = implode('', array_slice($characters, 0, $limit));
		$wordBoundary = preg_replace('/\s+\S*$/u', '', $excerpt);
		return trim(is_string($wordBoundary) && $wordBoundary !== '' ? $wordBoundary : $excerpt) . '…';
	}
}
