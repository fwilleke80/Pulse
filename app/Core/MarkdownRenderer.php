<?php

/**
 * @file MarkdownRenderer.php
 * @brief Dependency-free safe Markdown rendering for portal content, previews, and email.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Renders Pulse's deliberately small Markdown subset without permitting raw HTML.
 *
 * The supported subset covers headings, paragraphs, emphasis, strong text, links,
 * ordered and unordered lists, blockquotes, horizontal rules, inline code, fenced
 * code blocks, and CommonMark-style hard line breaks from two trailing spaces. Input is always escaped before markup is emitted, and links are limited
 * to safe schemes.
 */
final class MarkdownRenderer
{
	/** @brief Renders Markdown for normal Pulse web pages. */
	public function ToHtml(string $markdown): string
	{
		return $this->RenderBlocks($markdown, false);
	}

	/** @brief Renders Markdown as conservative email HTML with inline CSS only. */
	public function ToEmailHtml(string $markdown): string
	{
		$content = $this->RenderBlocks($markdown, true);

		return '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif;font-size:16px;line-height:1.6;color:#20242a;">'
			. $content
			. '</div>';
	}

	/** @brief Produces a readable plain-text MIME alternative from Markdown source. */
	public function ToPlainText(string $markdown): string
	{
		$text = $this->Normalize($markdown);
		$text = preg_replace('/^```[^\n]*\n?/m', '', $text) ?? $text;
		$text = preg_replace('/^```\s*$/m', '', $text) ?? $text;
		$text = preg_replace('/^\s{0,3}#{1,6}\s+/m', '', $text) ?? $text;
		$text = preg_replace('/^\s{0,3}>\s?/m', '', $text) ?? $text;
		$text = preg_replace('/^\s{0,3}[-+*]\s+/m', '- ', $text) ?? $text;
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\(([^)\s]+)(?:\s+["\'][^"\']*["\'])?\)/',
			static function (array $match): string
			{
				$label = trim((string)$match[1]);
				$url = trim((string)$match[2]);
				return $label === $url ? $label : $label . ' (' . $url . ')';
			},
			$text
		) ?? $text;
		$text = preg_replace('/(\*\*|__)(.*?)\1/s', '$2', $text) ?? $text;
		$text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '$1', $text) ?? $text;
		$text = preg_replace('/(?<!_)_([^_\n]+)_(?!_)/', '$1', $text) ?? $text;
		$text = preg_replace('/`([^`\n]+)`/', '$1', $text) ?? $text;
		$text = preg_replace('/^\s{0,3}([-*_])(?:\s*\1){2,}\s*$/m', str_repeat('-', 32), $text) ?? $text;
		$text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
		return trim($text);
	}

	/** @brief Renders Markdown block structure. */
	private function RenderBlocks(string $markdown, bool $email): string
	{
		$lines = explode("\n", $this->Normalize($markdown));
		$html = [];
		$count = count($lines);
		$index = 0;

		while ($index < $count)
		{
			$line = $lines[$index];

			if (trim($line) === '')
			{
				$index++;
				continue;
			}

			if (preg_match('/^\s{0,3}```(?:[^`]*)$/', $line) === 1)
			{
				$index++;
				$code = [];

				while ($index < $count && preg_match('/^\s{0,3}```\s*$/', $lines[$index]) !== 1)
				{
					$code[] = $lines[$index];
					$index++;
				}

				if ($index < $count)
				{
					$index++;
				}

				$style = $email
					? ' style="margin:0 0 16px;padding:12px;overflow:auto;background:#f3f4f6;border:1px solid #dfe3e8;border-radius:6px;font-family:SFMono-Regular,Consolas,Liberation Mono,monospace;font-size:14px;line-height:1.45;white-space:pre-wrap;"'
					: '';
				$html[] = '<pre' . $style . '><code>' . $this->Escape(implode("\n", $code)) . '</code></pre>';
				continue;
			}

			if (preg_match('/^\s{0,3}(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $heading) === 1)
			{
				$level = strlen((string)$heading[1]);
				$styles = [
					1 => 'font-size:28px;line-height:1.25;margin:0 0 16px;',
					2 => 'font-size:24px;line-height:1.3;margin:0 0 14px;',
					3 => 'font-size:20px;line-height:1.35;margin:0 0 12px;',
					4 => 'font-size:18px;line-height:1.4;margin:0 0 10px;',
					5 => 'font-size:16px;line-height:1.45;margin:0 0 8px;',
					6 => 'font-size:15px;line-height:1.45;margin:0 0 8px;',
				];
				$style = $email ? ' style="' . $styles[$level] . 'font-weight:700;color:#20242a;"' : '';
				$html[] = '<h' . $level . $style . '>' . $this->RenderInline((string)$heading[2], $email) . '</h' . $level . '>';
				$index++;
				continue;
			}

			if ($this->IsHorizontalRule($line))
			{
				$style = $email ? ' style="border:0;border-top:1px solid #dfe3e8;margin:20px 0;"' : '';
				$html[] = '<hr' . $style . '>';
				$index++;
				continue;
			}

			if (preg_match('/^\s{0,3}>\s?(.*)$/', $line) === 1)
			{
				$quoteLines = [];

				while ($index < $count && preg_match('/^\s{0,3}>\s?(.*)$/', $lines[$index], $quote) === 1)
				{
					$quoteLines[] = (string)$quote[1];
					$index++;
				}

				$style = $email ? ' style="margin:0 0 16px;padding:2px 0 2px 14px;border-left:4px solid #c8cdd3;color:#4b5563;"' : '';
				$html[] = '<blockquote' . $style . '>' . $this->RenderBlocks(implode("\n", $quoteLines), $email) . '</blockquote>';
				continue;
			}

			if (preg_match('/^\s{0,3}[-+*]\s+(.+)$/', $line) === 1)
			{
				$items = [];

				while ($index < $count && preg_match('/^\s{0,3}[-+*]\s+(.+)$/', $lines[$index], $item) === 1)
				{
					$items[] = '<li' . ($email ? ' style="margin:0 0 4px;"' : '') . '>' . $this->RenderInline((string)$item[1], $email) . '</li>';
					$index++;
				}

				$style = $email ? ' style="margin:0 0 16px;padding-left:24px;"' : '';
				$html[] = '<ul' . $style . '>' . implode('', $items) . '</ul>';
				continue;
			}

			if (preg_match('/^\s{0,3}\d+[.)]\s+(.+)$/', $line) === 1)
			{
				$items = [];

				while ($index < $count && preg_match('/^\s{0,3}\d+[.)]\s+(.+)$/', $lines[$index], $item) === 1)
				{
					$items[] = '<li' . ($email ? ' style="margin:0 0 4px;"' : '') . '>' . $this->RenderInline((string)$item[1], $email) . '</li>';
					$index++;
				}

				$style = $email ? ' style="margin:0 0 16px;padding-left:24px;"' : '';
				$html[] = '<ol' . $style . '>' . implode('', $items) . '</ol>';
				continue;
			}

			$paragraph = [$line];
			$index++;

			while ($index < $count && trim($lines[$index]) !== '' && !$this->StartsBlock($lines[$index]))
			{
				$paragraph[] = $lines[$index];
				$index++;
			}

			$style = $email ? ' style="margin:0 0 16px;"' : '';
			$html[] = '<p' . $style . '>' . $this->RenderParagraph($paragraph, $email) . '</p>';
		}

		return implode("\n", $html);
	}

	/**
	 * @brief Renders paragraph lines while honoring two-space Markdown hard breaks.
	 * @param array<int, string> $lines Paragraph source lines.
	 * @param bool $email Whether email-specific inline styles are required.
	 */
	private function RenderParagraph(array $lines, bool $email): string
	{
		$result = '';
		$count = count($lines);

		foreach ($lines as $index => $line)
		{
			$hardBreak = preg_match('/ {2,}$/', $line) === 1;
			$result .= $this->RenderInline(trim($line), $email);

			if ($index + 1 < $count)
			{
				$result .= $hardBreak ? '<br>' : ' ';
			}
		}

		return $result;
	}

	/** @brief Renders safe inline Markdown after reserving code spans. */
	private function RenderInline(string $text, bool $email): string
	{
		$tokens = [];
		$text = preg_replace_callback(
			'/`([^`\n]+)`/',
			function (array $match) use (&$tokens, $email): string
			{
				$token = "\x1A" . count($tokens) . "\x1A";
				$style = $email ? ' style="padding:1px 4px;background:#f3f4f6;border-radius:4px;font-family:SFMono-Regular,Consolas,Liberation Mono,monospace;font-size:0.92em;"' : '';
				$tokens[$token] = '<code' . $style . '>' . $this->Escape((string)$match[1]) . '</code>';
				return $token;
			},
			$text
		) ?? $text;

		$text = $this->Escape($text);
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\(([^)\s]+)(?:\s+&quot;[^&]*&quot;)?\)/',
			function (array $match) use ($email): string
			{
				$url = html_entity_decode((string)$match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

				if (!$this->IsSafeUrl($url))
				{
					return (string)$match[1];
				}

				$style = $email ? ' style="color:#1769aa;text-decoration:underline;"' : '';
				return '<a href="' . $this->Escape($url) . '"' . $style . ' rel="noopener noreferrer">' . (string)$match[1] . '</a>';
			},
			$text
		) ?? $text;
		$text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text) ?? $text;
		$text = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $text) ?? $text;
		$text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;
		$text = preg_replace('/(?<!_)_([^_\n]+)_(?!_)/', '<em>$1</em>', $text) ?? $text;

		foreach ($tokens as $token => $html)
		{
			$text = str_replace($this->Escape($token), $html, $text);
		}

		return $text;
	}

	/** @brief Returns whether a line begins a Markdown block handled by this renderer. */
	private function StartsBlock(string $line): bool
	{
		return preg_match('/^\s{0,3}(?:#{1,6}\s+|```|>\s?|[-+*]\s+|\d+[.)]\s+)/', $line) === 1
			|| $this->IsHorizontalRule($line);
	}

	/** @brief Returns whether a line is a Markdown horizontal rule. */
	private function IsHorizontalRule(string $line): bool
	{
		return preg_match('/^\s{0,3}(?:\*\s*){3,}$/', $line) === 1
			|| preg_match('/^\s{0,3}(?:-\s*){3,}$/', $line) === 1
			|| preg_match('/^\s{0,3}(?:_\s*){3,}$/', $line) === 1;
	}

	/** @brief Normalizes line endings without otherwise changing source text. */
	private function Normalize(string $text): string
	{
		return str_replace(["\r\n", "\r"], "\n", $text);
	}

	/** @brief Escapes untrusted Markdown input for HTML output. */
	private function Escape(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/** @brief Allows only ordinary web and mail links in rendered Markdown. */
	private function IsSafeUrl(string $url): bool
	{
		$url = trim($url);

		if ($url === '' || preg_match('/[\x00-\x20\x7F]/', $url) === 1)
		{
			return false;
		}

		$scheme = parse_url($url, PHP_URL_SCHEME);

		if ($scheme === null || $scheme === false || $scheme === '')
		{
			return str_starts_with($url, '/') || str_starts_with($url, './') || str_starts_with($url, '../') || str_starts_with($url, '#');
		}

		return in_array(strtolower((string)$scheme), ['http', 'https', 'mailto'], true);
	}
}
