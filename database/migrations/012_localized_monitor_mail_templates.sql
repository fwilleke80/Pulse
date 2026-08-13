-- Pulse 0.7.5: language-specific monitor-wide mail templates.

CREATE TABLE monitor_mail_templates
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_id BIGINT UNSIGNED NOT NULL,
	template_key ENUM('recipient_default','safety_invitation','safety_reminder') NOT NULL,
	locale VARCHAR(10) NOT NULL,
	subject VARCHAR(255) NOT NULL,
	body_text LONGTEXT NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uq_monitor_mail_templates_monitor_key_locale (monitor_id, template_key, locale),
	INDEX idx_monitor_mail_templates_monitor (monitor_id),
	FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Preserve existing language-independent custom text without guessing which language it was
-- written in: copy it to both currently supported locales. Owners can then translate either
-- variant independently in the editor.
INSERT INTO monitor_mail_templates (monitor_id, template_key, locale, subject, body_text)
SELECT id, 'recipient_default', 'en', default_message_subject, default_message_body
FROM monitors
WHERE COALESCE(default_message_subject, '') <> '' AND COALESCE(default_message_body, '') <> '';

INSERT INTO monitor_mail_templates (monitor_id, template_key, locale, subject, body_text)
SELECT id, 'recipient_default', 'de', default_message_subject, default_message_body
FROM monitors
WHERE COALESCE(default_message_subject, '') <> '' AND COALESCE(default_message_body, '') <> '';

INSERT INTO monitor_mail_templates (monitor_id, template_key, locale, subject, body_text)
SELECT id, 'safety_invitation', 'en', safety_invitation_subject, safety_invitation_body
FROM monitors
WHERE COALESCE(safety_invitation_subject, '') <> '' AND COALESCE(safety_invitation_body, '') <> '';

INSERT INTO monitor_mail_templates (monitor_id, template_key, locale, subject, body_text)
SELECT id, 'safety_invitation', 'de', safety_invitation_subject, safety_invitation_body
FROM monitors
WHERE COALESCE(safety_invitation_subject, '') <> '' AND COALESCE(safety_invitation_body, '') <> '';

INSERT INTO monitor_mail_templates (monitor_id, template_key, locale, subject, body_text)
SELECT id, 'safety_reminder', 'en', safety_reminder_subject, safety_reminder_body
FROM monitors
WHERE COALESCE(safety_reminder_subject, '') <> '' AND COALESCE(safety_reminder_body, '') <> '';

INSERT INTO monitor_mail_templates (monitor_id, template_key, locale, subject, body_text)
SELECT id, 'safety_reminder', 'de', safety_reminder_subject, safety_reminder_body
FROM monitors
WHERE COALESCE(safety_reminder_subject, '') <> '' AND COALESCE(safety_reminder_body, '') <> '';
