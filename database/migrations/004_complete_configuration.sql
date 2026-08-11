-- Pulse 0.4.0 complete monitor configuration

ALTER TABLE contacts
	ADD COLUMN email_checked_at DATETIME NULL AFTER email;

ALTER TABLE monitors
	ADD COLUMN default_message_subject VARCHAR(255) NULL AFTER description,
	ADD COLUMN default_message_body LONGTEXT NULL AFTER default_message_subject;

ALTER TABLE contact_messages
	ADD UNIQUE KEY uq_contact_messages_monitor_contact (monitor_contact_id);
