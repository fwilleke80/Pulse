-- Pulse 0.5.0 reliable check-in lifecycle

ALTER TABLE monitors
	ADD COLUMN paused_at DATETIME NULL AFTER is_paused;

UPDATE monitors
SET paused_at = COALESCE(updated_at, UTC_TIMESTAMP())
WHERE is_paused = 1;

ALTER TABLE check_cycles
	MODIFY COLUMN status ENUM('pending','scheduled','awaiting','overdue','escalated','confirmed','cancelled') NOT NULL DEFAULT 'scheduled',
	ADD COLUMN due_at DATETIME NULL AFTER started_at,
	CHANGE COLUMN expires_at response_deadline_at DATETIME NOT NULL,
	ADD COLUMN reminder_interval_days INT UNSIGNED NOT NULL DEFAULT 1 AFTER response_deadline_at,
	ADD COLUMN max_reminders INT UNSIGNED NOT NULL DEFAULT 0 AFTER reminder_interval_days,
	ADD COLUMN overdue_at DATETIME NULL AFTER confirmed_at,
	ADD COLUMN cancelled_at DATETIME NULL AFTER escalated_at,
	ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER cancelled_at;

UPDATE check_cycles
SET due_at = started_at
WHERE due_at IS NULL;

UPDATE check_cycles cc
INNER JOIN monitors m ON m.id = cc.monitor_id
SET
	cc.reminder_interval_days = m.reminder_interval_days,
	cc.max_reminders = m.max_reminders;

UPDATE check_cycles
SET status = 'awaiting'
WHERE status = 'pending';

UPDATE check_cycles older
INNER JOIN check_cycles newer
	ON newer.monitor_id = older.monitor_id
	AND newer.id > older.id
	AND newer.status IN ('scheduled','awaiting','overdue','escalated')
SET
	older.status = 'cancelled',
	older.cancelled_at = UTC_TIMESTAMP()
WHERE older.status IN ('scheduled','awaiting','overdue','escalated');

UPDATE check_cycles cc
INNER JOIN monitors m ON m.id = cc.monitor_id
SET
	cc.status = 'cancelled',
	cc.cancelled_at = COALESCE(m.paused_at, UTC_TIMESTAMP())
WHERE m.is_paused = 1
	AND cc.status IN ('scheduled','awaiting','overdue','escalated');

ALTER TABLE check_cycles
	MODIFY COLUMN due_at DATETIME NOT NULL,
	MODIFY COLUMN status ENUM('scheduled','awaiting','overdue','escalated','confirmed','cancelled') NOT NULL DEFAULT 'scheduled',
	ADD INDEX idx_check_cycles_runtime (monitor_id, status, due_at);

INSERT INTO check_cycles
(
	monitor_id,
	status,
	started_at,
	due_at,
	response_deadline_at,
	reminder_interval_days,
	max_reminders,
	reminders_sent,
	updated_at
)
SELECT
	m.id,
	CASE
		WHEN COALESCE(m.next_check_due_at, UTC_TIMESTAMP()) <= UTC_TIMESTAMP() THEN 'awaiting'
		ELSE 'scheduled'
	END,
	COALESCE(m.last_confirmed_at, m.created_at, UTC_TIMESTAMP()),
	COALESCE(m.next_check_due_at, UTC_TIMESTAMP()),
	TIMESTAMPADD(DAY, m.response_window_days, COALESCE(m.next_check_due_at, UTC_TIMESTAMP())),
	m.reminder_interval_days,
	m.max_reminders,
	0,
	UTC_TIMESTAMP()
FROM monitors m
WHERE m.is_paused = 0
	AND NOT EXISTS
	(
		SELECT 1
		FROM check_cycles cc
		WHERE cc.monitor_id = m.id
			AND cc.status IN ('scheduled','awaiting','overdue','escalated')
	);
