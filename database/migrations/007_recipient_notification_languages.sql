-- Pulse 0.6.2 recipient-specific notification languages

ALTER TABLE users
	ADD COLUMN notification_locale VARCHAR(10) NULL AFTER display_name;

ALTER TABLE contacts
	ADD COLUMN notification_locale VARCHAR(10) NULL AFTER email;
