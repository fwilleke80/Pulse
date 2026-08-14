CREATE TABLE IF NOT EXISTS monitor_portal_templates
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_id BIGINT UNSIGNED NOT NULL,
	locale VARCHAR(10) NOT NULL,
	message_text LONGTEXT NULL,
	intro_text LONGTEXT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uq_monitor_portal_templates_monitor_locale (monitor_id, locale),
	INDEX idx_monitor_portal_templates_monitor (monitor_id),
	FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_portal_messages
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_contact_id BIGINT UNSIGNED NOT NULL,
	body_text LONGTEXT NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uq_contact_portal_messages_monitor_contact (monitor_contact_id),
	FOREIGN KEY (monitor_contact_id)
		REFERENCES monitor_contacts(id)
		ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE recipient_release_deliveries
	ADD COLUMN portal_intro_text LONGTEXT NULL AFTER body_text,
	ADD COLUMN portal_message_text LONGTEXT NULL AFTER portal_intro_text;

-- Existing 0.8.0/0.8.1/0.8.2 deliveries reused the notification body in the portal.
-- Preserve the useful text while removing the redacted portal-link marker.
UPDATE recipient_release_deliveries
SET portal_message_text = NULLIF(TRIM(REPLACE(body_text, '[Recipient portal link redacted]', '')), '')
WHERE portal_message_text IS NULL;
