-- @brief Add owner-controlled emergency shutdown and delayed, owner-warned releases.
ALTER TABLE monitors
  ADD COLUMN release_grace_hours SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER safety_confirmation_days,
  ADD COLUMN emergency_stopped_at DATETIME NULL AFTER paused_at,
  ADD COLUMN emergency_was_paused TINYINT(1) NOT NULL DEFAULT 0 AFTER emergency_stopped_at;

CREATE TABLE release_warnings
(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  check_cycle_id BIGINT UNSIGNED NOT NULL,
  monitor_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  release_not_before DATETIME NULL,
  grace_hours SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  warning_sent_at DATETIME NULL,
  stopped_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_release_warnings_cycle (check_cycle_id),
  UNIQUE KEY uq_release_warnings_token (token_hash),
  INDEX idx_release_warnings_owner (user_id, created_at),
  FOREIGN KEY (check_cycle_id) REFERENCES check_cycles(id) ON DELETE CASCADE,
  FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
