-- Pulse 1.2.1: four separately checked email addresses for owners and contacts.

ALTER TABLE users
	ADD COLUMN email_checked_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER email,
	ADD COLUMN email_2 VARCHAR(255) NULL AFTER email_checked_at,
	ADD COLUMN email_2_checked_at DATETIME NULL AFTER email_2,
	ADD COLUMN email_3 VARCHAR(255) NULL AFTER email_2_checked_at,
	ADD COLUMN email_3_checked_at DATETIME NULL AFTER email_3,
	ADD COLUMN email_4 VARCHAR(255) NULL AFTER email_3_checked_at,
	ADD COLUMN email_4_checked_at DATETIME NULL AFTER email_4;

UPDATE users
SET email_checked_at = COALESCE(email_checked_at, UTC_TIMESTAMP());

ALTER TABLE contacts
	ADD COLUMN email_2 VARCHAR(255) NULL AFTER email_checked_at,
	ADD COLUMN email_2_checked_at DATETIME NULL AFTER email_2,
	ADD COLUMN email_3 VARCHAR(255) NULL AFTER email_2_checked_at,
	ADD COLUMN email_3_checked_at DATETIME NULL AFTER email_3,
	ADD COLUMN email_4 VARCHAR(255) NULL AFTER email_3_checked_at,
	ADD COLUMN email_4_checked_at DATETIME NULL AFTER email_4;

CREATE TABLE safety_contact_request_emails
(
	safety_request_id BIGINT UNSIGNED NOT NULL,
	sort_order TINYINT UNSIGNED NOT NULL,
	email VARCHAR(255) NOT NULL,
	PRIMARY KEY (safety_request_id, sort_order),
	UNIQUE KEY uq_safety_request_email (safety_request_id, email),
	FOREIGN KEY (safety_request_id) REFERENCES safety_contact_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO safety_contact_request_emails (safety_request_id, sort_order, email)
SELECT id, 1, contact_email
FROM safety_contact_requests;

CREATE TABLE recipient_release_delivery_emails
(
	recipient_delivery_id BIGINT UNSIGNED NOT NULL,
	sort_order TINYINT UNSIGNED NOT NULL,
	email VARCHAR(255) NOT NULL,
	PRIMARY KEY (recipient_delivery_id, sort_order),
	UNIQUE KEY uq_recipient_delivery_email (recipient_delivery_id, email),
	FOREIGN KEY (recipient_delivery_id) REFERENCES recipient_release_deliveries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO recipient_release_delivery_emails (recipient_delivery_id, sort_order, email)
SELECT id, 1, recipient_email
FROM recipient_release_deliveries;
