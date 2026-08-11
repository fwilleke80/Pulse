CREATE TABLE IF NOT EXISTS users
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	email VARCHAR(255) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	display_name VARCHAR(255) NOT NULL,
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	last_login_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	name VARCHAR(255) NOT NULL,
	email VARCHAR(255) NOT NULL,
	cell_phone VARCHAR(50) NULL,
	notes TEXT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	INDEX idx_contacts_user (user_id),
	CONSTRAINT fk_contacts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monitors
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	name VARCHAR(255) NOT NULL,
	description TEXT NULL,
	check_interval_days INT UNSIGNED NOT NULL,
	response_window_days INT UNSIGNED NOT NULL,
	reminder_interval_days INT UNSIGNED NOT NULL,
	max_reminders INT UNSIGNED NOT NULL DEFAULT 0,
	is_paused TINYINT(1) NOT NULL DEFAULT 0,
	last_confirmed_at DATETIME NULL,
	next_check_due_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	INDEX idx_monitors_user (user_id),
	INDEX idx_monitors_due (is_paused, next_check_due_at),
	CONSTRAINT fk_monitors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monitor_contacts
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_id BIGINT UNSIGNED NOT NULL,
	contact_id BIGINT UNSIGNED NOT NULL,
	sort_order INT UNSIGNED NOT NULL DEFAULT 1,
	UNIQUE KEY uq_monitor_contacts_monitor_contact (monitor_id, contact_id),
	INDEX idx_monitor_contacts_contact (contact_id),
	CONSTRAINT fk_monitor_contacts_monitor FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
	CONSTRAINT fk_monitor_contacts_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_contact_id BIGINT UNSIGNED NOT NULL,
	subject VARCHAR(255) NOT NULL,
	body_text LONGTEXT NOT NULL,
	CONSTRAINT fk_contact_messages_monitor_contact FOREIGN KEY (monitor_contact_id) REFERENCES monitor_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_id BIGINT UNSIGNED NOT NULL,
	title VARCHAR(255) NOT NULL,
	storage_type ENUM('text','file') NOT NULL,
	text_content LONGTEXT NULL,
	stored_filename VARCHAR(255) NULL,
	original_filename VARCHAR(255) NULL,
	mime_type VARCHAR(255) NULL,
	file_size_bytes BIGINT UNSIGNED NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	INDEX idx_documents_monitor (monitor_id),
	CONSTRAINT fk_documents_monitor FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_monitor_contacts
(
	document_id BIGINT UNSIGNED NOT NULL,
	monitor_contact_id BIGINT UNSIGNED NOT NULL,
	PRIMARY KEY (document_id, monitor_contact_id),
	INDEX idx_document_monitor_contacts_contact (monitor_contact_id),
	CONSTRAINT fk_document_monitor_contacts_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
	CONSTRAINT fk_document_monitor_contacts_monitor_contact FOREIGN KEY (monitor_contact_id) REFERENCES monitor_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS check_cycles
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	monitor_id BIGINT UNSIGNED NOT NULL,
	status ENUM('pending','confirmed','escalated') NOT NULL DEFAULT 'pending',
	started_at DATETIME NOT NULL,
	expires_at DATETIME NOT NULL,
	reminders_sent INT UNSIGNED NOT NULL DEFAULT 0,
	confirmed_at DATETIME NULL,
	escalated_at DATETIME NULL,
	INDEX idx_check_cycles_monitor (monitor_id),
	CONSTRAINT fk_check_cycles_monitor FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_tokens
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	token_hash CHAR(64) NOT NULL UNIQUE,
	token_purpose VARCHAR(50) NOT NULL,
	check_cycle_id BIGINT UNSIGNED NULL,
	contact_id BIGINT UNSIGNED NULL,
	document_id BIGINT UNSIGNED NULL,
	expires_at DATETIME NOT NULL,
	used_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_access_tokens_expiry (expires_at),
	CONSTRAINT fk_access_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_queue
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	check_cycle_id BIGINT UNSIGNED NULL,
	contact_id BIGINT UNSIGNED NULL,
	mail_type VARCHAR(50) NOT NULL,
	recipient_email VARCHAR(255) NOT NULL,
	subject VARCHAR(255) NOT NULL,
	body_text LONGTEXT NOT NULL,
	status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
	available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	sent_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_mail_queue_delivery (status, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_log
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	mail_type VARCHAR(50) NOT NULL,
	recipient_email VARCHAR(255) NOT NULL,
	subject VARCHAR(255) NOT NULL,
	send_status ENUM('sent','failed') NOT NULL,
	error_message TEXT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	event_type VARCHAR(100) NOT NULL,
	entity_type VARCHAR(100) NULL,
	entity_id BIGINT UNSIGNED NULL,
	message TEXT NOT NULL,
	context_json JSON NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_audit_log_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	setting_key VARCHAR(100) NOT NULL,
	setting_value TEXT NULL,
	UNIQUE KEY uq_app_settings_user_key (user_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
