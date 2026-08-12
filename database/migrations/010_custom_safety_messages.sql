-- Pulse 0.7.2 custom safety-contact email text

ALTER TABLE monitors
	ADD COLUMN safety_invitation_subject VARCHAR(255) NULL AFTER safety_confirmation_days,
	ADD COLUMN safety_invitation_body LONGTEXT NULL AFTER safety_invitation_subject,
	ADD COLUMN safety_reminder_subject VARCHAR(255) NULL AFTER safety_invitation_body,
	ADD COLUMN safety_reminder_body LONGTEXT NULL AFTER safety_reminder_subject;

ALTER TABLE safety_contact_requests
	ADD COLUMN invitation_subject VARCHAR(255) NULL AFTER notification_locale,
	ADD COLUMN invitation_body LONGTEXT NULL AFTER invitation_subject,
	ADD COLUMN reminder_subject VARCHAR(255) NULL AFTER invitation_body,
	ADD COLUMN reminder_body LONGTEXT NULL AFTER reminder_subject;
