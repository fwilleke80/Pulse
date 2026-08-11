-- Pulse 0.4.2 reference database schema
-- MySQL 8+ / MariaDB 10.6+
-- Pulse applies database/migrations automatically. Do not import this reference file over an existing database.
-- ----
-- Core user and contact data
-- Monitor configuration and monitor-contact assignments
-- Per-contact monitor messages
-- Monitor documents and document recipient assignments
-- Future runtime/check-cycle/mail/audit tables

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
	is_paused TINYINT(1) NOT NULL DEFAULT 0,
	last_confirmed_at DATETIME NULL,
	next_check_due_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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

CREATE TABLE documents
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_id BIGINT UNSIGNED NOT NULL,
	title VARCHAR(255) NOT NULL,
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
	status ENUM('pending','confirmed','escalated') DEFAULT 'pending',
	started_at DATETIME NOT NULL,
	expires_at DATETIME NOT NULL,
	reminders_sent INT DEFAULT 0,
	confirmed_at DATETIME NULL,
	escalated_at DATETIME NULL,
	FOREIGN KEY (monitor_id)
		REFERENCES monitors(id)
		ON DELETE CASCADE
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
	check_cycle_id BIGINT NULL,
	contact_id BIGINT NULL,
	mail_type VARCHAR(50) NOT NULL,
	recipient_email VARCHAR(255) NOT NULL,
	subject VARCHAR(255) NOT NULL,
	body_text LONGTEXT NOT NULL,
	status ENUM('queued','sent','failed') DEFAULT 'queued',
	available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	sent_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mail_log
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	mail_type VARCHAR(50) NOT NULL,
	recipient_email VARCHAR(255) NOT NULL,
	subject VARCHAR(255) NOT NULL,
	send_status ENUM('sent','failed') NOT NULL,
	error_message TEXT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
