-- @brief Preserve configured monitor recipients when temporarily inactive.
ALTER TABLE monitor_contacts
  ADD COLUMN is_assigned TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order,
  ADD INDEX idx_monitor_contacts_active (monitor_id, is_assigned, sort_order);
