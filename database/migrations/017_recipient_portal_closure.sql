-- Pulse 0.8.9: distinguish recipient-initiated permanent portal closure from owner revocation.

ALTER TABLE recipient_release_deliveries
	ADD COLUMN portal_closed_by_recipient_at DATETIME NULL AFTER portal_revoked_at;
