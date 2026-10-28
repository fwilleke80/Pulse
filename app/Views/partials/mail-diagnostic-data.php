<?php
/** @brief Builds saved template issues separately from live draft field markers. */
$mailDraftGroups = [];
if (isset($mailTemplates, $monitor))
{
	$families = [
		'owner_due_notice' => ['owner', 'monitors.messages.owner.due.heading', false],
		'owner_reminder' => ['owner', 'monitors.messages.owner.reminder.heading', false],
		'recipient_default' => ['recipient', 'monitors.messages.default.heading', true],
		'safety_invitation' => ['safety', 'monitors.escalation.messages.invitation.heading', true],
		'safety_reminder' => ['safety', 'monitors.escalation.messages.reminder.heading', true],
	];
	foreach ($families as $kind => [$section, $labelKey, $requireUrl])
	{
		foreach ($section === 'owner' ? ['owner'] : $availableLocales as $language)
		{
			$template = $mailTemplates[$kind][$language] ?? [];
			$suffix = $language === 'owner' ? '' : '_' . language_field_suffix($language);
			$affected = [];
			if ($section === 'recipient')
			{
				foreach ($monitorContacts as $contact)
				{
					if (!empty($contact['is_assigned']) && ($contact['notification_locale'] ?? '') === $language && !isset($messageOverrides[(int)$contact['id']]))
					{
						$affected[] = (string)$contact['name'];
					}
				}
			}
			$mailDraftGroups[] = [
				'subject' => $kind . '_subject' . $suffix, 'body' => $kind . '_body' . $suffix,
				'section' => $section, 'language' => $language === 'owner' ? '' : $language,
				'label' => __($labelKey) . ($language === 'owner' ? '' : ' · ' . notification_language_name($language)),
				'requireUrl' => $requireUrl, 'valueSubject' => (string)($template['subject'] ?? ''),
				'valueBody' => (string)($template['body_text'] ?? ''), 'affected' => $affected,
				'url' => $base_url . '/monitors/edit?id=' . (int)$monitor['id'] . '&tab=messages&section=' . $section . '&language=' . rawurlencode($language),
			];
		}
	}
	foreach ($monitorContacts as $contact)
	{
		$template = $messageOverrides[(int)$contact['id']] ?? null;
		if (empty($contact['is_assigned']) || !is_array($template))
		{
			continue;
		}
		$mailDraftGroups[] = ['subject' => 'message_subject', 'body' => 'message_body', 'section' => 'notification', 'language' => '', 'label' => (string)$contact['name'] . ' · ' . __('recipients.overview.personal'), 'requireUrl' => true, 'valueSubject' => (string)$template['subject'], 'valueBody' => (string)$template['body_text'], 'affected' => [(string)$contact['name']], 'url' => $base_url . '/monitors/recipients/edit?id=' . (int)$contact['id'] . '&section=notification'];
	}
}
elseif (isset($recipient))
{
	$mailDraftGroups[] = ['subject' => 'message_subject', 'body' => 'message_body', 'section' => 'notification', 'language' => '', 'label' => __('recipients.message.heading'), 'requireUrl' => true, 'valueSubject' => (string)($recipient['override_subject'] ?? ''), 'valueBody' => (string)($recipient['override_body'] ?? ''), 'toggle' => '[data-message-override-toggle]', 'enabled' => $hasOverride, 'affected' => [], 'url' => $base_url . '/monitors/recipients/edit?id=' . (int)$recipient['id'] . '&section=notification'];
}
if (isset($recipient) && !$hasOverride)
{
	$language = (string)$recipient['notification_locale'];
	$mailDraftGroups[] = ['subject' => 'recipient_default_subject_' . language_field_suffix($language), 'body' => 'recipient_default_body_' . language_field_suffix($language), 'section' => 'notification', 'language' => $language, 'label' => __('monitors.messages.default.heading') . ' · ' . notification_language_name($language), 'requireUrl' => true, 'valueSubject' => (string)($recipient['default_message_subject'] ?? ''), 'valueBody' => (string)($recipient['default_message_body'] ?? ''), 'affected' => [(string)$recipient['name']], 'url' => $base_url . '/monitors/edit?id=' . (int)$recipient['monitor_id'] . '&tab=messages&section=recipient&language=' . rawurlencode($language)];
}

$savedMailIssues = [];
foreach ($mailDraftGroups as $group)
{
	if (($group['enabled'] ?? true) === false)
	{
		continue;
	}
	foreach (\Pulse\Services\TemplateDiagnostics::Fields($group['valueSubject'], $group['valueBody'], $group['requireUrl']) as $field => $code)
	{
		$savedMailIssues[] = ['group' => $group, 'field' => $group[$field], 'code' => $code];
	}
}
