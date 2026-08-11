CREATE TABLE IF NOT EXISTS login_attempts
(
	attempt_key CHAR(64) NOT NULL PRIMARY KEY,
	attempts INT UNSIGNED NOT NULL DEFAULT 0,
	window_started_at DATETIME NOT NULL,
	blocked_until DATETIME NULL,
	updated_at DATETIME NOT NULL,
	INDEX idx_login_attempts_blocked_until (blocked_until),
	INDEX idx_login_attempts_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE monitors
SET
	last_confirmed_at = COALESCE(last_confirmed_at, created_at),
	next_check_due_at = COALESCE(
		next_check_due_at,
		TIMESTAMPADD(DAY, check_interval_days, COALESCE(last_confirmed_at, created_at))
	)
WHERE last_confirmed_at IS NULL OR next_check_due_at IS NULL;
