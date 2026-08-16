<?php

/**
 * @file helpers.php
 * @brief Escaping, translation, CSRF, and date helpers for Pulse views.
 * @author Frank Willeke
 */

declare(strict_types=1);

use Pulse\Core\CsrfTokenManager;
use Pulse\Core\LanguageCatalog;
use Pulse\Core\MarkdownRenderer;
use Pulse\Core\NotificationLanguage;
use Pulse\Core\Translator;

$__pulseTranslator = null;
$__pulseCsrfTokenManager = null;
$__pulseLanguageCatalog = null;
$__pulseMarkdownRenderer = null;
$__pulseNotificationLanguage = null;
$__pulseDisplayTimezone = 'Europe/Berlin';

/** @brief Escapes a string for safe HTML output. @param string $value Input string. @return string */
function e(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @brief Registers the global translator. @param Translator $translator Translator instance. */
function setTranslator(Translator $translator): void
{
	global $__pulseTranslator;
	$__pulseTranslator = $translator;
}


/** @brief Registers the Markdown renderer used by views and editor previews. @param MarkdownRenderer $renderer Renderer instance. */
function setMarkdownRenderer(MarkdownRenderer $renderer): void
{
	global $__pulseMarkdownRenderer;
	$__pulseMarkdownRenderer = $renderer;
}

/** @brief Renders trusted renderer output from untrusted Markdown source. @param string $markdown Markdown source. @return string Safe HTML. */
function markdown_html(string $markdown): string
{
	global $__pulseMarkdownRenderer;

	if (!$__pulseMarkdownRenderer instanceof MarkdownRenderer)
	{
		return '<p>' . nl2br(e($markdown)) . '</p>';
	}

	return $__pulseMarkdownRenderer->ToHtml($markdown);
}

/** @brief Renders email-styled trusted renderer output from untrusted Markdown source. @param string $markdown Markdown source. @return string Safe HTML. */
function markdown_email_html(string $markdown): string
{
	global $__pulseMarkdownRenderer;

	if (!$__pulseMarkdownRenderer instanceof MarkdownRenderer)
	{
		return '<p>' . nl2br(e($markdown)) . '</p>';
	}

	return $__pulseMarkdownRenderer->ToEmailHtml($markdown);
}

/**
 * @brief Builds the shared Edit/Preview Markdown field used throughout Pulse.
 * @param string $baseUrl Application base URL.
 * @param string $id Textarea ID.
 * @param string $name Textarea form field name.
 * @param string $value Markdown source.
 * @param int $rows Textarea row count.
 * @param string $mode Preview mode: web or email.
 * @param array<string, string|bool> $attributes Additional textarea attributes.
 * @return string Editor HTML.
 */
function markdown_editor(
	string $baseUrl,
	string $id,
	string $name,
	string $value,
	int $rows = 8,
	string $mode = 'web',
	array $attributes = []
): string
{
	$mode = $mode === 'email' ? 'email' : 'web';
	$attributeText = '';

	foreach ($attributes as $attribute => $attributeValue)
	{
		if ($attributeValue === false)
		{
			continue;
		}

		$attributeText .= ' ' . e($attribute);

		if ($attributeValue !== true)
		{
			$attributeText .= '="' . e((string)$attributeValue) . '"';
		}
	}

	$editorId = $id . '_markdown_editor';
	$editTabId = $editorId . '_edit_tab';
	$previewTabId = $editorId . '_preview_tab';
	$editPanelId = $editorId . '_edit_panel';
	$previewPanelId = $editorId . '_preview_panel';
	$previewClass = $mode === 'email' ? 'markdown-preview-output markdown-preview-email markdown-content' : 'markdown-preview-output markdown-content';

	return '<div class="markdown-editor" data-markdown-editor data-preview-mode="' . e($mode) . '" data-preview-url="' . e(rtrim($baseUrl, '/') . '/markdown/preview') . '" data-preview-loading="' . e__('markdown.editor.loading') . '" data-preview-error="' . e__('markdown.editor.error') . '">'
		. '<div class="markdown-editor-tabs" role="tablist" aria-label="' . e__('markdown.editor.tabs_label') . '">'
		. '<button type="button" id="' . e($editTabId) . '" class="markdown-editor-tab is-active" role="tab" aria-selected="true" aria-controls="' . e($editPanelId) . '" data-markdown-edit-tab>' . e__('markdown.editor.edit') . '</button>'
		. '<button type="button" id="' . e($previewTabId) . '" class="markdown-editor-tab" role="tab" aria-selected="false" aria-controls="' . e($previewPanelId) . '" data-markdown-preview-tab>' . e__('markdown.editor.preview') . '</button>'
		. '</div>'
		. '<div id="' . e($editPanelId) . '" role="tabpanel" aria-labelledby="' . e($editTabId) . '" data-markdown-edit-panel>'
		. '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="' . max(2, $rows) . '"' . $attributeText . '>' . e($value) . '</textarea>'
		. '</div>'
		. '<div id="' . e($previewPanelId) . '" class="markdown-preview-panel" role="tabpanel" aria-labelledby="' . e($previewTabId) . '" data-markdown-preview-panel hidden>'
		. '<div class="' . $previewClass . '" data-markdown-preview-output></div>'
		. '</div>'
		. '</div>'
		. '<p class="form-hint markdown-editor-hint">' . e__('markdown.editor.hint') . '</p>';
}

/** @brief Registers the global CSRF token manager. @param CsrfTokenManager $manager Token manager. */
function setCsrfTokenManager(CsrfTokenManager $manager): void
{
	global $__pulseCsrfTokenManager;
	$__pulseCsrfTokenManager = $manager;
}

/** @brief Sets the timezone used to format UTC database timestamps. @param string $timezone IANA timezone. */
function setDisplayTimezone(string $timezone): void
{
	global $__pulseDisplayTimezone;
	$__pulseDisplayTimezone = $timezone;
}


/** @brief Registers the installed-language catalog used by views. @param LanguageCatalog $catalog Language catalog. */
function setLanguageCatalog(LanguageCatalog $catalog): void
{
	global $__pulseLanguageCatalog;
	$__pulseLanguageCatalog = $catalog;
}

/**
 * @brief Returns the native display name for an installed locale.
 * @param string $locale Locale identifier.
 * @return string Human-readable language name.
 */
function language_name(string $locale): string
{
	global $__pulseLanguageCatalog;

	if ($__pulseLanguageCatalog instanceof LanguageCatalog)
	{
		return $__pulseLanguageCatalog->Name($locale);
	}

	return $locale;
}

/** @brief Returns a form-safe suffix for a locale identifier. @param string $locale Locale identifier. @return string */
function language_field_suffix(string $locale): string
{
	$suffix = preg_replace('/[^a-z0-9_]/i', '_', $locale);
	return is_string($suffix) ? $suffix : $locale;
}

/** @brief Registers the notification-language resolver used by views. @param NotificationLanguage $resolver Notification-language resolver. */
function setNotificationLanguageResolver(NotificationLanguage $resolver): void
{
	global $__pulseNotificationLanguage;
	$__pulseNotificationLanguage = $resolver;
}

/**
 * @brief Returns a translated language name with the deployment default as fallback.
 * @param string|null $locale Stored notification locale.
 * @return string Human-readable language name.
 */
function notification_language_name(?string $locale): string
{
	global $__pulseNotificationLanguage;

	if ($__pulseNotificationLanguage instanceof NotificationLanguage)
	{
		$locale = $__pulseNotificationLanguage->Resolve($locale);
	}
	else
	{
		$locale = is_string($locale) && trim($locale) !== '' ? trim($locale) : 'de';
	}

	return language_name((string)$locale);
}

/**
 * @brief Performs a raw translation.
 * @param string $key Translation key.
 * @param array<string, scalar|null> $params Translation parameters.
 * @return string
 */
function __(string $key, array $params = []): string
{
	global $__pulseTranslator;

	if (!$__pulseTranslator instanceof Translator)
	{
		return $key;
	}

	$text = $__pulseTranslator->Translate($key);

	foreach ($params as $name => $value)
	{
		$text = str_replace('{' . $name . '}', (string)$value, $text);
	}

	return $text;
}

/** @brief Returns an HTML-escaped translation. @param string $key Translation key. @param array<string, scalar|null> $params Translation parameters. @return string */
function e__(string $key, array $params = []): string
{
	return e(__($key, $params));
}

/** @brief Returns a hidden CSRF field for a state-changing form. @return string */
function csrf_field(): string
{
	global $__pulseCsrfTokenManager;

	if (!$__pulseCsrfTokenManager instanceof CsrfTokenManager)
	{
		throw new RuntimeException('CSRF token manager has not been initialized.');
	}

	return '<input type="hidden" name="_csrf_token" value="' . e($__pulseCsrfTokenManager->Token()) . '">' . PHP_EOL;
}

/** @brief Abbreviates a string to a maximum length. @param string $text Input text. @param int $maxLength Maximum length. @return string */
function abbrev(string $text, int $maxLength): string
{
	if ($maxLength <= 3 || strlen($text) <= $maxLength)
	{
		return $text;
	}

	return substr($text, 0, $maxLength - 3) . '...';
}

/**
 * @brief Formats a UTC database timestamp in the configured display timezone.
 * @param string|null $value UTC timestamp.
 * @param string $fallback Returned for empty or invalid input.
 * @return string
 */
function format_datetime(?string $value, string $fallback = '—'): string
{
	global $__pulseDisplayTimezone;

	if ($value === null || trim($value) === '')
	{
		return $fallback;
	}

	try
	{
		$date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
		return $date->setTimezone(new DateTimeZone($__pulseDisplayTimezone))->format('Y-m-d H:i');
	}
	catch (Throwable)
	{
		return $fallback;
	}
}

/**
 * @brief Formats the remaining delay before a retrying mail becomes eligible.
 * @param string|null $value UTC available-at timestamp.
 * @return string Localized relative wait such as "in 8 min".
 */
function format_retry_wait(?string $value): string
{
	if ($value === null || trim($value) === '')
	{
		return __('profile.notifications.queue_details.wait.less_than_minute');
	}

	try
	{
		$available = new DateTimeImmutable($value, new DateTimeZone('UTC'));
		$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
		$seconds = max(0, $available->getTimestamp() - $now->getTimestamp());

		if ($seconds < 60)
		{
			return __('profile.notifications.queue_details.wait.less_than_minute');
		}

		$minutes = (int)ceil($seconds / 60);

		if ($minutes < 60)
		{
			return __('profile.notifications.queue_details.wait.minutes', ['count' => $minutes]);
		}

		$hours = intdiv($minutes, 60);
		$remainingMinutes = $minutes % 60;

		if ($remainingMinutes === 0)
		{
			return __('profile.notifications.queue_details.wait.hours', ['count' => $hours]);
		}

		return __('profile.notifications.queue_details.wait.hours_minutes', [
			'hours' => $hours,
			'minutes' => $remainingMinutes,
		]);
	}
	catch (Throwable)
	{
		return __('profile.notifications.queue_details.wait.less_than_minute');
	}
}

/**
 * @brief Returns whether a monitor is active and currently due.
 * @param array<string, mixed> $monitor Monitor row.
 * @return bool
 */
function is_monitor_due(array $monitor): bool
{
	return in_array(monitor_status($monitor), ['awaiting', 'safety-pending', 'overdue'], true);
}

/**
 * @brief Returns the user-facing monitor state identifier.
 * @param array<string, mixed> $monitor Monitor row.
 * @return string One of checked-in, awaiting, safety-pending, overdue, escalated, paused, or archived.
 */
function monitor_status(array $monitor): string
{
	if (!empty($monitor['is_archived']))
	{
		return 'archived';
	}

	if (!empty($monitor['is_paused']))
	{
		return 'paused';
	}

	$cycleStatus = (string)($monitor['latest_cycle_status'] ?? '');

	if ($cycleStatus === 'scheduled')
	{
		return 'checked-in';
	}

	if ($cycleStatus === 'safety_pending')
	{
		return 'safety-pending';
	}

	if (in_array($cycleStatus, ['awaiting', 'overdue', 'escalated'], true))
	{
		return $cycleStatus;
	}

	$nextDue = $monitor['next_check_due_at'] ?? null;

	if (!is_string($nextDue) || $nextDue === '')
	{
		return 'awaiting';
	}

	$dueTimestamp = strtotime($nextDue . ' UTC');

	if ($dueTimestamp === false || $dueTimestamp > time())
	{
		return 'checked-in';
	}

	return 'awaiting';
}
