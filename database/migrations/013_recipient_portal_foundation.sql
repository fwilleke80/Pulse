-- Pulse 0.8.0: secure recipient portal invitation and short-lived access-code foundation.

ALTER TABLE monitors
	ADD COLUMN recipient_portal_expiry_days INT UNSIGNED NULL AFTER safety_reminder_body;

ALTER TABLE recipient_release_deliveries
	ADD COLUMN portal_token_hash CHAR(64) NULL AFTER notification_locale,
	ADD COLUMN portal_availability_days INT UNSIGNED NULL AFTER portal_token_hash,
	ADD COLUMN portal_released_at DATETIME NULL AFTER portal_availability_days,
	ADD COLUMN portal_expires_at DATETIME NULL AFTER portal_released_at,
	ADD COLUMN portal_revoked_at DATETIME NULL AFTER portal_expires_at,
	ADD COLUMN portal_last_access_at DATETIME NULL AFTER portal_revoked_at,
	ADD UNIQUE KEY uq_recipient_release_deliveries_portal_token (portal_token_hash),
	ADD INDEX idx_recipient_release_deliveries_portal_runtime (portal_token_hash, portal_revoked_at, portal_expires_at);

CREATE TABLE recipient_portal_codes
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	recipient_delivery_id BIGINT UNSIGNED NOT NULL,
	code_hash VARCHAR(255) NOT NULL,
	attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
	expires_at DATETIME NOT NULL,
	sent_at DATETIME NULL,
	used_at DATETIME NULL,
	invalidated_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	INDEX idx_recipient_portal_codes_delivery (recipient_delivery_id, created_at),
	INDEX idx_recipient_portal_codes_runtime (recipient_delivery_id, expires_at, used_at, invalidated_at),
	CONSTRAINT fk_recipient_portal_codes_delivery FOREIGN KEY (recipient_delivery_id) REFERENCES recipient_release_deliveries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE mail_queue
	ADD COLUMN recipient_portal_code_id BIGINT UNSIGNED NULL AFTER recipient_delivery_id,
	ADD INDEX idx_mail_queue_recipient_portal_code (recipient_portal_code_id);
