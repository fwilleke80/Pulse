-- Pulse 1.2.3: optional TOTP two-factor authentication and one-time recovery codes.

CREATE TABLE user_totp_credentials
(
	security_method_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
	secret_ciphertext TEXT NOT NULL,
	last_used_counter BIGINT UNSIGNED NULL,
	enabled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (security_method_id) REFERENCES user_security_methods(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_totp_recovery_codes
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	security_method_id BIGINT UNSIGNED NOT NULL,
	code_hash CHAR(64) NOT NULL,
	used_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_totp_recovery_code_hash (code_hash),
	INDEX idx_totp_recovery_method_unused (security_method_id, used_at),
	FOREIGN KEY (security_method_id) REFERENCES user_security_methods(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
