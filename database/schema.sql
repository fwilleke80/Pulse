-- Pulse 0.8.5 reference database schema
-- MySQL 8+ / MariaDB 10.6+
-- Pulse applies database/migrations automatically. Do not import this reference file over an existing database.
-- ----
-- Core user and contact data
-- Monitor configuration and monitor-contact assignments
-- Per-contact monitor messages
-- Monitor documents and document recipient assignments
-- Persisted check-in, safety-contact, recipient-mail, and future document-access tables

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE schema_migrations
(
	migration VARCHAR(255) NOT NULL PRIMARY KEY,
	checksum CHAR(64) NOT NULL,
	applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	email VARCHAR(255) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	display_name VARCHAR(255) NOT NULL,
	role ENUM('user','administrator') NOT NULL DEFAULT 'user',
	notification_locale VARCHAR(10) NULL,
	website_locale VARCHAR(10) NULL,
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	last_login_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contacts
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	name VARCHAR(255) NOT NULL,
	email VARCHAR(255) NOT NULL,
	notification_locale VARCHAR(10) NULL,
	email_checked_at DATETIME NULL,
	cell_phone VARCHAR(50) NULL,
	notes TEXT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE monitors
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	name VARCHAR(255) NOT NULL,
	description TEXT NULL,
	default_message_subject VARCHAR(255) NULL,
	default_message_body LONGTEXT NULL,
	check_interval_days INT NOT NULL,
	response_window_days INT NOT NULL,
	reminder_interval_days INT NOT NULL,
	max_reminders INT NOT NULL DEFAULT 0,
	escalation_policy ENUM('direct','safety_contact') NOT NULL DEFAULT 'direct',
	safety_response_window_days INT UNSIGNED NOT NULL DEFAULT 3,
	safety_reminder_interval_days INT UNSIGNED NOT NULL DEFAULT 1,
	safety_max_reminders INT UNSIGNED NOT NULL DEFAULT 1,
	safety_required_confirmations INT UNSIGNED NOT NULL DEFAULT 1,
	safety_confirmation_days INT UNSIGNED NULL,
	safety_invitation_subject VARCHAR(255) NULL,
	safety_invitation_body LONGTEXT NULL,
	safety_reminder_subject VARCHAR(255) NULL,
	safety_reminder_body LONGTEXT NULL,
	recipient_portal_expiry_days INT UNSIGNED NULL,
	is_paused TINYINT(1) NOT NULL DEFAULT 0,
	paused_at DATETIME NULL,
	last_confirmed_at DATETIME NULL,
	last_safety_confirmed_at DATETIME NULL,
	last_safety_contact_id BIGINT UNSIGNED NULL,
	next_check_due_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE monitor_portal_templates
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

CREATE TABLE monitor_safety_contacts
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_id BIGINT UNSIGNED NOT NULL,
	contact_id BIGINT UNSIGNED NOT NULL,
	sort_order INT UNSIGNED NOT NULL DEFAULT 1,
	UNIQUE KEY uq_monitor_safety_contacts_monitor_contact (monitor_id, contact_id),
	INDEX idx_monitor_safety_contacts_contact (contact_id),
	FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
	FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE monitor_contacts
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_id BIGINT UNSIGNED NOT NULL,
	contact_id BIGINT UNSIGNED NOT NULL,
	sort_order INT DEFAULT 1,
	FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
	FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
	UNIQUE(monitor_id, contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contact_messages
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_contact_id BIGINT UNSIGNED NOT NULL,
	subject VARCHAR(255) NOT NULL,
	body_text LONGTEXT NOT NULL,
	UNIQUE KEY uq_contact_messages_monitor_contact (monitor_contact_id),
	FOREIGN KEY (monitor_contact_id)
		REFERENCES monitor_contacts(id)
		ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contact_portal_messages
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

CREATE TABLE documents
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_id BIGINT UNSIGNED NOT NULL,
	title VARCHAR(255) NOT NULL,
	description TEXT NULL,
	storage_type ENUM('text','file') NOT NULL,
	text_content LONGTEXT NULL,
	stored_filename VARCHAR(255) NULL,
	original_filename VARCHAR(255) NULL,
	mime_type VARCHAR(255) NULL,
	file_size_bytes BIGINT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Documents belong to a monitor.
-- Recipient assignment is modeled separately through document_monitor_contacts,
-- which links each document to one or more monitor_contacts.
CREATE TABLE document_monitor_contacts
(
	document_id BIGINT UNSIGNED NOT NULL,
	monitor_contact_id BIGINT UNSIGNED NOT NULL,
	PRIMARY KEY (document_id, monitor_contact_id),
	FOREIGN KEY (document_id)
		REFERENCES documents(id)
		ON DELETE CASCADE,
	FOREIGN KEY (monitor_contact_id)
		REFERENCES monitor_contacts(id)
		ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE check_cycles
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_id BIGINT UNSIGNED NOT NULL,
	status ENUM('scheduled','awaiting','safety_pending','overdue','escalated','confirmed','cancelled') NOT NULL DEFAULT 'scheduled',
	started_at DATETIME NOT NULL,
	due_at DATETIME NOT NULL,
	response_deadline_at DATETIME NOT NULL,
	reminder_interval_days INT UNSIGNED NOT NULL DEFAULT 1,
	max_reminders INT UNSIGNED NOT NULL DEFAULT 0,
	escalation_policy_snapshot ENUM('direct','safety_contact') NOT NULL DEFAULT 'direct',
	safety_response_window_days INT UNSIGNED NOT NULL DEFAULT 3,
	safety_reminder_interval_days INT UNSIGNED NOT NULL DEFAULT 1,
	safety_max_reminders INT UNSIGNED NOT NULL DEFAULT 1,
	safety_required_confirmations INT UNSIGNED NOT NULL DEFAULT 1,
	safety_confirmation_days INT UNSIGNED NOT NULL DEFAULT 1,
	reminders_sent INT UNSIGNED NOT NULL DEFAULT 0,
	due_notice_sent_at DATETIME NULL,
	last_reminder_sent_at DATETIME NULL,
	safety_gate_started_at DATETIME NULL,
	safety_gate_deadline_at DATETIME NULL,
	safety_confirmed_at DATETIME NULL,
	safety_confirmation_count INT UNSIGNED NOT NULL DEFAULT 0,
	confirmed_at DATETIME NULL,
	overdue_at DATETIME NULL,
	escalated_at DATETIME NULL,
	cancelled_at DATETIME NULL,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	INDEX idx_check_cycles_monitor (monitor_id),
	INDEX idx_check_cycles_runtime (monitor_id, status, due_at),
	FOREIGN KEY (monitor_id)
		REFERENCES monitors(id)
		ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE safety_contact_requests
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	check_cycle_id BIGINT UNSIGNED NOT NULL,
	monitor_id BIGINT UNSIGNED NOT NULL,
	contact_id BIGINT UNSIGNED NULL,
	contact_name VARCHAR(255) NOT NULL,
	contact_email VARCHAR(255) NOT NULL,
	notification_locale VARCHAR(10) NOT NULL,
	invitation_subject VARCHAR(255) NULL,
	invitation_body LONGTEXT NULL,
	reminder_subject VARCHAR(255) NULL,
	reminder_body LONGTEXT NULL,
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
	FOREIGN KEY (check_cycle_id) REFERENCES check_cycles(id) ON DELETE CASCADE,
	FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
	FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE safety_request_tokens
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	safety_request_id BIGINT UNSIGNED NOT NULL,
	token_hash CHAR(64) NOT NULL,
	expires_at DATETIME NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_safety_request_tokens_hash (token_hash),
	INDEX idx_safety_request_tokens_request (safety_request_id),
	FOREIGN KEY (safety_request_id) REFERENCES safety_contact_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
	FOREIGN KEY (check_cycle_id) REFERENCES check_cycles(id) ON DELETE CASCADE,
	FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
	portal_token_hash CHAR(64) NULL,
	portal_availability_days INT UNSIGNED NULL,
	portal_released_at DATETIME NULL,
	portal_expires_at DATETIME NULL,
	portal_revoked_at DATETIME NULL,
	portal_closed_by_recipient_at DATETIME NULL,
	portal_last_access_at DATETIME NULL,
	subject VARCHAR(255) NOT NULL,
	body_text LONGTEXT NOT NULL,
	portal_intro_text LONGTEXT NULL,
	portal_message_text LONGTEXT NULL,
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
	UNIQUE KEY uq_recipient_release_deliveries_portal_token (portal_token_hash),
	INDEX idx_recipient_release_deliveries_portal_runtime (portal_token_hash, portal_revoked_at, portal_expires_at),
	FOREIGN KEY (release_id) REFERENCES recipient_releases(id) ON DELETE CASCADE,
	FOREIGN KEY (check_cycle_id) REFERENCES check_cycles(id) ON DELETE CASCADE,
	FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
	FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipient_delivery_documents
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	recipient_delivery_id BIGINT UNSIGNED NOT NULL,
	source_document_id BIGINT UNSIGNED NULL,
	title VARCHAR(255) NOT NULL,
	description TEXT NULL,
	storage_type ENUM('text','file') NOT NULL,
	text_content LONGTEXT NULL,
	stored_filename VARCHAR(255) NULL,
	original_filename VARCHAR(255) NULL,
	mime_type VARCHAR(255) NULL,
	file_size_bytes BIGINT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_recipient_delivery_documents_source (recipient_delivery_id, source_document_id),
	INDEX idx_recipient_delivery_documents_delivery (recipient_delivery_id, id),
	INDEX idx_recipient_delivery_documents_stored_file (stored_filename),
	FOREIGN KEY (recipient_delivery_id)
		REFERENCES recipient_release_deliveries(id)
		ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
	FOREIGN KEY (recipient_delivery_id) REFERENCES recipient_release_deliveries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE access_tokens
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	token_hash CHAR(64) NOT NULL UNIQUE,
	token_purpose VARCHAR(50) NOT NULL,
	check_cycle_id BIGINT NULL,
	contact_id BIGINT NULL,
	document_id BIGINT NULL,
	expires_at DATETIME NOT NULL,
	used_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mail_queue
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	check_cycle_id BIGINT UNSIGNED NULL,
	monitor_id BIGINT UNSIGNED NULL,
	contact_id BIGINT UNSIGNED NULL,
	safety_request_id BIGINT UNSIGNED NULL,
	recipient_delivery_id BIGINT UNSIGNED NULL,
	recipient_portal_code_id BIGINT UNSIGNED NULL,
	mail_type VARCHAR(50) NOT NULL,
	idempotency_key VARCHAR(191) NOT NULL,
	reminder_number INT UNSIGNED NULL,
	recipient_email VARCHAR(255) NOT NULL,
	subject VARCHAR(255) NOT NULL,
	body_text LONGTEXT NOT NULL,
	status ENUM('queued','retrying','processing','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
	attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
	max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
	last_error TEXT NULL,
	available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	locked_at DATETIME NULL,
	locked_until DATETIME NULL,
	locked_by VARCHAR(64) NULL,
	lease_token CHAR(32) NULL,
	sent_at DATETIME NULL,
	failed_at DATETIME NULL,
	cancelled_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uq_mail_queue_idempotency (idempotency_key),
	INDEX idx_mail_queue_delivery (status, available_at),
	INDEX idx_mail_queue_claim (status, available_at, locked_until),
	INDEX idx_mail_queue_cycle (check_cycle_id, mail_type, reminder_number),
	INDEX idx_mail_queue_safety_request (safety_request_id),
	INDEX idx_mail_queue_recipient_delivery (recipient_delivery_id),
	INDEX idx_mail_queue_recipient_portal_code (recipient_portal_code_id),
	INDEX idx_mail_queue_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mail_log
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	queue_id BIGINT UNSIGNED NULL,
	user_id BIGINT UNSIGNED NOT NULL,
	check_cycle_id BIGINT UNSIGNED NULL,
	mail_type VARCHAR(50) NOT NULL,
	recipient_email VARCHAR(255) NOT NULL,
	subject VARCHAR(255) NOT NULL,
	attempt_number INT UNSIGNED NOT NULL DEFAULT 1,
	status ENUM('sent','retrying','failed') NOT NULL,
	error_message TEXT NULL,
	smtp_message TEXT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_mail_log_queue (queue_id),
	INDEX idx_mail_log_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	event_type VARCHAR(100) NOT NULL,
	entity_type VARCHAR(100) NULL,
	entity_id BIGINT NULL,
	message TEXT NOT NULL,
	context_json JSON NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE app_settings
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	setting_key VARCHAR(100) NOT NULL,
	setting_value TEXT NULL,
	UNIQUE(user_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts
(
	attempt_key CHAR(64) NOT NULL PRIMARY KEY,
	attempts INT UNSIGNED NOT NULL DEFAULT 0,
	window_started_at DATETIME NOT NULL,
	blocked_until DATETIME NULL,
	updated_at DATETIME NOT NULL,
	INDEX idx_login_attempts_blocked_until (blocked_until),
	INDEX idx_login_attempts_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
