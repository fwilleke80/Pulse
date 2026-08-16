-- Pulse 1.2.2: preserve disabled recipient-specific portal messages as reusable drafts.

ALTER TABLE contact_portal_messages
	ADD COLUMN is_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER body_text;
