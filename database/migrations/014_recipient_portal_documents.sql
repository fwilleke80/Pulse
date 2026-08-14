CREATE TABLE IF NOT EXISTS recipient_delivery_documents
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	recipient_delivery_id BIGINT UNSIGNED NOT NULL,
	source_document_id BIGINT UNSIGNED NULL,
	title VARCHAR(255) NOT NULL,
	description TEXT NULL,
	storage_type ENUM('text','file') NOT NULL,
	text_content LONGTEXT NULL,
	stored_filename VARCHAR(255) NULL,
	original_filename VARCHAR(255) NULL,
	mime_type VARCHAR(255) NULL,
	file_size_bytes BIGINT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_recipient_delivery_documents_source (recipient_delivery_id, source_document_id),
	INDEX idx_recipient_delivery_documents_delivery (recipient_delivery_id, id),
	INDEX idx_recipient_delivery_documents_stored_file (stored_filename),
	FOREIGN KEY (recipient_delivery_id)
		REFERENCES recipient_release_deliveries(id)
		ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Best-effort backfill for deliveries created by 0.8.0 before document snapshots existed.
INSERT IGNORE INTO recipient_delivery_documents
(
	recipient_delivery_id,
	source_document_id,
	title,
	description,
	storage_type,
	text_content,
	stored_filename,
	original_filename,
	mime_type,
	file_size_bytes,
	created_at
)
SELECT
	rrd.id,
	d.id,
	d.title,
	d.description,
	d.storage_type,
	d.text_content,
	d.stored_filename,
	d.original_filename,
	d.mime_type,
	d.file_size_bytes,
	UTC_TIMESTAMP()
FROM recipient_release_deliveries rrd
INNER JOIN monitor_contacts mc
	ON mc.monitor_id = rrd.monitor_id
	AND mc.contact_id = rrd.contact_id
INNER JOIN document_monitor_contacts dmc
	ON dmc.monitor_contact_id = mc.id
INNER JOIN documents d
	ON d.id = dmc.document_id
WHERE rrd.status = 'sent';
