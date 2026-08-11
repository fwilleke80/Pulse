-- Pulse 0.6.0 notification infrastructure

ALTER TABLE check_cycles
	ADD COLUMN last_reminder_sent_at DATETIME NULL AFTER reminders_sent;

ALTER TABLE mail_queue
	MODIFY COLUMN check_cycle_id BIGINT UNSIGNED NULL,
	ADD COLUMN monitor_id BIGINT UNSIGNED NULL AFTER check_cycle_id,
	ADD COLUMN idempotency_key VARCHAR(191) NULL AFTER mail_type,
	ADD COLUMN reminder_number INT UNSIGNED NULL AFTER idempotency_key,
	MODIFY COLUMN status ENUM('queued','retrying','processing','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
	ADD COLUMN attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
	ADD COLUMN max_attempts INT UNSIGNED NOT NULL DEFAULT 5 AFTER attempt_count,
	ADD COLUMN last_error TEXT NULL AFTER max_attempts,
	ADD COLUMN locked_at DATETIME NULL AFTER available_at,
	ADD COLUMN locked_until DATETIME NULL AFTER locked_at,
	ADD COLUMN locked_by VARCHAR(64) NULL AFTER locked_until,
	ADD COLUMN lease_token CHAR(32) NULL AFTER locked_by,
	ADD COLUMN failed_at DATETIME NULL AFTER sent_at,
	ADD COLUMN cancelled_at DATETIME NULL AFTER failed_at,
	ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE mail_queue
SET idempotency_key = CONCAT('legacy:', id)
WHERE idempotency_key IS NULL;

ALTER TABLE mail_queue
	MODIFY COLUMN idempotency_key VARCHAR(191) NOT NULL,
	ADD UNIQUE KEY uq_mail_queue_idempotency (idempotency_key),
	ADD INDEX idx_mail_queue_claim (status, available_at, locked_until),
	ADD INDEX idx_mail_queue_cycle (check_cycle_id, mail_type, reminder_number),
	ADD INDEX idx_mail_queue_user_created (user_id, created_at);

ALTER TABLE mail_log
	ADD COLUMN queue_id BIGINT UNSIGNED NULL AFTER id,
	ADD COLUMN check_cycle_id BIGINT UNSIGNED NULL AFTER user_id,
	ADD COLUMN attempt_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER subject,
	CHANGE COLUMN send_status status ENUM('sent','retrying','failed') NOT NULL,
	ADD COLUMN smtp_message TEXT NULL AFTER error_message,
	ADD INDEX idx_mail_log_queue (queue_id),
	ADD INDEX idx_mail_log_user_created (user_id, created_at);
