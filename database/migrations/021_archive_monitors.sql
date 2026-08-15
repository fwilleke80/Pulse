-- Pulse 0.9.6 escalated-monitor lifecycle cleanup.
ALTER TABLE monitors
	ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER paused_at,
	ADD COLUMN archived_at DATETIME NULL AFTER is_archived,
	ADD INDEX idx_monitors_user_archived (user_id, is_archived);
