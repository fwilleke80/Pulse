-- Pulse 0.6.3 immediate check-in due notifications

ALTER TABLE check_cycles
	ADD COLUMN due_notice_sent_at DATETIME NULL AFTER reminders_sent;

-- A cycle that already delivered at least one 0.6.2 reminder must not receive
-- an additional late due notice during upgrade. New and not-yet-notified
-- awaiting cycles keep NULL and receive the due notice on the next cron run.
UPDATE check_cycles
SET due_notice_sent_at = COALESCE(last_reminder_sent_at, due_at)
WHERE reminders_sent > 0
	AND due_notice_sent_at IS NULL;
