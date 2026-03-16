-- Converts documents from the old structure to the current monitor-based model
-- and introduces document_monitor_contacts for per-recipient assignment.

ALTER TABLE documents
	DROP FOREIGN KEY documents_ibfk_1,
	DROP COLUMN user_id;

ALTER TABLE documents
	ADD COLUMN original_filename VARCHAR(255) NULL AFTER stored_filename,
	ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE TABLE document_monitor_contacts
(
	document_id BIGINT UNSIGNED NOT NULL,
	monitor_contact_id BIGINT UNSIGNED NOT NULL,
	PRIMARY KEY (document_id, monitor_contact_id),
	CONSTRAINT fk_document_monitor_contacts_document
		FOREIGN KEY (document_id) REFERENCES documents(id)
		ON DELETE CASCADE,
	CONSTRAINT fk_document_monitor_contacts_monitor_contact
		FOREIGN KEY (monitor_contact_id) REFERENCES monitor_contacts(id)
		ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;