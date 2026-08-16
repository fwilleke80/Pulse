ALTER TABLE monitors
	ADD COLUMN location_check_in_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER recipient_portal_expiry_days,
	ADD COLUMN portal_location_sharing_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER location_check_in_enabled,
	ADD COLUMN portal_location_history_limit TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER portal_location_sharing_enabled;

CREATE TABLE check_in_locations
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	audit_log_id BIGINT UNSIGNED NOT NULL,
	check_cycle_id BIGINT UNSIGNED NOT NULL,
	monitor_id BIGINT UNSIGNED NOT NULL,
	user_id BIGINT UNSIGNED NOT NULL,
	latitude DECIMAL(10,7) NOT NULL,
	longitude DECIMAL(10,7) NOT NULL,
	accuracy_meters DECIMAL(12,2) NOT NULL,
	address_label VARCHAR(1000) NULL,
	created_at DATETIME NOT NULL,
	UNIQUE KEY uq_check_in_locations_audit (audit_log_id),
	UNIQUE KEY uq_check_in_locations_cycle (check_cycle_id),
	INDEX idx_check_in_locations_monitor_created (monitor_id, created_at),
	INDEX idx_check_in_locations_user_created (user_id, created_at),
	FOREIGN KEY (audit_log_id) REFERENCES audit_log(id) ON DELETE CASCADE,
	FOREIGN KEY (check_cycle_id) REFERENCES check_cycles(id) ON DELETE CASCADE,
	FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recipient_release_locations
(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	release_id BIGINT UNSIGNED NOT NULL,
	sequence_number INT UNSIGNED NOT NULL,
	latitude DECIMAL(10,7) NOT NULL,
	longitude DECIMAL(10,7) NOT NULL,
	accuracy_meters DECIMAL(12,2) NOT NULL,
	address_label VARCHAR(1000) NULL,
	checked_in_at DATETIME NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_recipient_release_location_sequence (release_id, sequence_number),
	INDEX idx_recipient_release_locations_release (release_id, checked_in_at),
	FOREIGN KEY (release_id) REFERENCES recipient_releases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
