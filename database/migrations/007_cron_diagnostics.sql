-- Pulse 1.3.1: retain cron-token change time and bounded unsuccessful-call diagnostics.

ALTER TABLE system_status
	ADD COLUMN cron_token_changed_at DATETIME NULL AFTER last_successful_cron_at;

CREATE TABLE cron_failures
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	attempted_at DATETIME NOT NULL,
	failure_code VARCHAR(32) NOT NULL,
	request_method VARCHAR(16) NOT NULL,
	provided_token VARCHAR(512) NULL,
	token_truncated TINYINT(1) NOT NULL DEFAULT 0,
	INDEX idx_cron_failures_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
