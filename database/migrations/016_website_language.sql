-- Pulse 0.8.4: persist the authenticated website/UI language independently from notification language.

ALTER TABLE users
	ADD COLUMN website_locale VARCHAR(10) NULL AFTER notification_locale;

UPDATE users
SET website_locale = notification_locale
WHERE website_locale IS NULL
	AND notification_locale IS NOT NULL
	AND notification_locale <> '';
