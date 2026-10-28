<?php
/** @file TemplateDiagnostics.php @brief Field-specific diagnostics for saveable mail drafts. */
declare(strict_types=1);
namespace Pulse\Services;
final class TemplateDiagnostics
{
	/** @brief Returns field/error pairs; empty pairs use built-in defaults. @return array<string,string> */
	public static function Fields(string $subject, string $body, bool $requireUrl = false): array
	{
		$subject = trim($subject);
		$body = trim($body);
		$errors = [];
		if ($subject === '' && $body !== '')
		{
			$errors['subject'] = 'subject_missing';
		}
		if ($body === '' && $subject !== '')
		{
			$errors['body'] = 'body_missing';
		}
		if ($requireUrl && $body !== '' && !str_contains($body, '{url}'))
		{
			$errors['body'] = 'url_missing';
		}
		if ($requireUrl && str_contains($subject, '{url}'))
		{
			$errors['subject'] = 'url_subject';
		}
		return $errors;
	}
}
