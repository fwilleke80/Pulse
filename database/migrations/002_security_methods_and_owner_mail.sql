ALTER TABLE monitor_mail_templates
	MODIFY COLUMN template_key ENUM('owner_due_notice','owner_reminder','recipient_default','safety_invitation','safety_reminder') NOT NULL;

CREATE TABLE user_security_profiles
(
	user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
	webauthn_user_handle VARCHAR(86) NOT NULL UNIQUE,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_security_methods
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	method VARCHAR(32) NOT NULL,
	label VARCHAR(255) NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	last_used_at DATETIME NULL,
	INDEX idx_security_methods_user_method (user_id, method),
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_passkey_credentials
(
	security_method_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
	credential_id_hash CHAR(64) NOT NULL UNIQUE,
	credential_id TEXT NOT NULL,
	public_key_pem TEXT NOT NULL,
	algorithm INT NOT NULL,
	sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
	transports VARCHAR(255) NULL,
	FOREIGN KEY (security_method_id) REFERENCES user_security_methods(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quick_checkin_tokens
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
	check_cycle_id BIGINT UNSIGNED NOT NULL,
	token_hash CHAR(64) NOT NULL UNIQUE,
	expires_at DATETIME NOT NULL,
	used_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_quick_checkin_user (user_id, used_at, expires_at),
	INDEX idx_quick_checkin_cycle (check_cycle_id),
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (check_cycle_id) REFERENCES check_cycles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
