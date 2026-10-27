-- Pulse 1.5.0: account lifecycle and protected original owner.
ALTER TABLE users
 ADD COLUMN is_super_user TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN email_verified_at DATETIME NULL,
 ADD COLUMN approved_at DATETIME NULL,
 ADD COLUMN totp_required TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN password_change_required TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN auth_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 ADD COLUMN super_user_slot TINYINT GENERATED ALWAYS AS (CASE WHEN is_super_user = 1 THEN 1 ELSE NULL END) STORED,
 ADD UNIQUE KEY uq_users_super_user (super_user_slot);

-- Preserve access for installed accounts. A fresh install's owner is set by the installer.
UPDATE users SET email_verified_at = COALESCE(email_checked_at, created_at), approved_at = created_at;
UPDATE users SET is_super_user = 1, role = 'administrator', is_active = 1
WHERE id = (SELECT id FROM (SELECT id FROM users ORDER BY created_at, id LIMIT 1) original_owner);

-- Serializes account mutations, including email uniqueness across all four slots.
CREATE TABLE account_write_lock
(
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO account_write_lock (id) VALUES (1);

CREATE TABLE account_tokens
(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 purpose ENUM('verify','invite','password_reset') NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 email VARCHAR(255) NOT NULL,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_account_token_hash (token_hash),
 INDEX idx_account_token_user (user_id, purpose),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE account_settings
(
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 registration_enabled TINYINT(1) NOT NULL DEFAULT 0,
 registration_requires_approval TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO account_settings (id) VALUES (1);

-- Repair the mixed pause/emergency state produced by the old Resume action.
-- Never restart a stopped monitor as a side effect of an upgrade.
UPDATE monitors SET is_paused = 1, paused_at = COALESCE(paused_at, emergency_stopped_at), next_check_due_at = NULL
WHERE emergency_stopped_at IS NOT NULL;
