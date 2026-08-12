-- Pulse 0.7.3 editable uploaded-file metadata

ALTER TABLE documents
	ADD COLUMN description TEXT NULL AFTER title;
