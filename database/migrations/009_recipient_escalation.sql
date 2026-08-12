-- Pulse 0.7.0 recipient notification and optional safety-contact escalation

ALTER TABLE monitors
	ADD COLUMN escalation_policy ENUM('direct','safety_contact') NOT NULL DEFAULT 'direct' AFTER max_reminders,
	ADD COLUMN safety_response_window_days INT UNSIGNED NOT NULL DEFAULT 3 AFTER escalation_policy,
	ADD COLUMN safety_reminder_interval_days INT UNSIGNED NOT NULL DEFAULT 1 AFTER safety_response_window_days,
	ADD COLUMN safety_max_reminders INT UNSIGNED NOT NULL DEFAULT 1 AFTER safety_reminder_interval_days,
	ADD COLUMN safety_required_confirmations INT UNSIGNED NOT NULL DEFAULT 1 AFTER safety_max_reminders,
	ADD COLUMN safety_confirmation_days INT UNSIGNED NULL AFTER safety_required_confirmations,
	ADD COLUMN last_safety_confirmed_at DATETIME NULL AFTER last_confirmed_at,
	ADD COLUMN last_safety_contact_id BIGINT UNSIGNED NULL AFTER last_safety_confirmed_at;

CREATE TABLE monitor_safety_contacts
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_id BIGINT UNSIGNED NOT NULL,
	contact_id BIGINT UNSIGNED NOT NULL,
	sort_order INT UNSIGNED NOT NULL DEFAULT 1,
	UNIQUE KEY uq_monitor_safety_contacts_monitor_contact (monitor_id, contact_id),
	INDEX idx_monitor_safety_contacts_contact (contact_id),
	CONSTRAINT fk_monitor_safety_contacts_monitor FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
	CONSTRAINT fk_monitor_safety_contacts_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE check_cycles
	MODIFY COLUMN status ENUM('scheduled','awaiting','safety_pending','overdue','escalated','confirmed','cancelled') NOT NULL DEFAULT 'scheduled',
	ADD COLUMN escalation_policy_snapshot ENUM('direct','safety_contact') NOT NULL DEFAULT 'direct' AFTER max_reminders,
	ADD COLUMN safety_response_window_days INT UNSIGNED NOT NULL DEFAULT 3 AFTER escalation_policy_snapshot,
	ADD COLUMN safety_reminder_interval_days INT UNSIGNED NOT NULL DEFAULT 1 AFTER safety_response_window_days,
	ADD COLUMN safety_max_reminders INT UNSIGNED NOT NULL DEFAULT 1 AFTER safety_reminder_interval_days,
	ADD COLUMN safety_required_confirmations INT UNSIGNED NOT NULL DEFAULT 1 AFTER safety_max_reminders,
	ADD COLUMN safety_confirmation_days INT UNSIGNED NOT NULL DEFAULT 1 AFTER safety_required_confirmations,
	ADD COLUMN safety_gate_started_at DATETIME NULL AFTER last_reminder_sent_at,
	ADD COLUMN safety_gate_deadline_at DATETIME NULL AFTER safety_gate_started_at,
	ADD COLUMN safety_confirmed_at DATETIME NULL AFTER safety_gate_deadline_at,
	ADD COLUMN safety_confirmation_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER safety_confirmed_at;

CREATE TABLE safety_contact_requests
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	check_cycle_id BIGINT UNSIGNED NOT NULL,
	monitor_id BIGINT UNSIGNED NOT NULL,
	contact_id BIGINT UNSIGNED NULL,
	contact_name VARCHAR(255) NOT NULL,
	contact_email VARCHAR(255) NOT NULL,
	notification_locale VARCHAR(10) NOT NULL,
	status ENUM('pending','confirmed','declined','expired','cancelled') NOT NULL DEFAULT 'pending',
	invitation_sent_at DATETIME NULL,
	reminders_sent INT UNSIGNED NOT NULL DEFAULT 0,
	last_reminder_sent_at DATETIME NULL,
	confirmed_at DATETIME NULL,
	declined_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uq_safety_contact_requests_cycle_contact (check_cycle_id, contact_id),
	INDEX idx_safety_contact_requests_runtime (check_cycle_id, status),
	CONSTRAINT fk_safety_contact_requests_cycle FOREIGN KEY (check_cycle_id) REFERENCES check_cycles(id) ON DELETE CASCADE,
	CONSTRAINT fk_safety_contact_requests_monitor FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
	CONSTRAINT fk_safety_contact_requests_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE safety_request_tokens
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	safety_request_id BIGINT UNSIGNED NOT NULL,
	token_hash CHAR(64) NOT NULL,
	expires_at DATETIME NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_safety_request_tokens_hash (token_hash),
	INDEX idx_safety_request_tokens_request (safety_request_id),
	CONSTRAINT fk_safety_request_tokens_request FOREIGN KEY (safety_request_id) REFERENCES safety_contact_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recipient_releases
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	check_cycle_id BIGINT UNSIGNED NOT NULL,
	monitor_id BIGINT UNSIGNED NOT NULL,
	user_id BIGINT UNSIGNED NOT NULL,
	status ENUM('blocked','pending','partial','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
	blocked_reason VARCHAR(100) NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	staged_at DATETIME NULL,
	first_sent_at DATETIME NULL,
	completed_at DATETIME NULL,
	cancelled_at DATETIME NULL,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uq_recipient_releases_cycle (check_cycle_id),
	INDEX idx_recipient_releases_monitor (monitor_id, created_at),
	CONSTRAINT fk_recipient_releases_cycle FOREIGN KEY (check_cycle_id) REFERENCES check_cycles(id) ON DELETE CASCADE,
	CONSTRAINT fk_recipient_releases_monitor FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
	CONSTRAINT fk_recipient_releases_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recipient_release_deliveries
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	release_id BIGINT UNSIGNED NOT NULL,
	check_cycle_id BIGINT UNSIGNED NOT NULL,
	monitor_id BIGINT UNSIGNED NOT NULL,
	contact_id BIGINT UNSIGNED NULL,
	recipient_name VARCHAR(255) NOT NULL,
	recipient_email VARCHAR(255) NOT NULL,
	notification_locale VARCHAR(10) NOT NULL,
	subject VARCHAR(255) NOT NULL,
	body_text LONGTEXT NOT NULL,
	status ENUM('queued','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
	queue_id BIGINT UNSIGNED NULL,
	last_error TEXT NULL,
	sent_at DATETIME NULL,
	failed_at DATETIME NULL,
	cancelled_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uq_recipient_release_deliveries_release_contact (release_id, contact_id),
	INDEX idx_recipient_release_deliveries_contact (monitor_id, contact_id, created_at),
	INDEX idx_recipient_release_deliveries_status (release_id, status),
	CONSTRAINT fk_recipient_release_deliveries_release FOREIGN KEY (release_id) REFERENCES recipient_releases(id) ON DELETE CASCADE,
	CONSTRAINT fk_recipient_release_deliveries_cycle FOREIGN KEY (check_cycle_id) REFERENCES check_cycles(id) ON DELETE CASCADE,
	CONSTRAINT fk_recipient_release_deliveries_monitor FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
	CONSTRAINT fk_recipient_release_deliveries_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE mail_queue
	ADD COLUMN safety_request_id BIGINT UNSIGNED NULL AFTER contact_id,
	ADD COLUMN recipient_delivery_id BIGINT UNSIGNED NULL AFTER safety_request_id,
	ADD INDEX idx_mail_queue_safety_request (safety_request_id),
	ADD INDEX idx_mail_queue_recipient_delivery (recipient_delivery_id);
