-- Pulse 0.9.0 administrator authorization foundation.

ALTER TABLE users
	ADD COLUMN role ENUM('user','administrator') NOT NULL DEFAULT 'user' AFTER display_name;

-- Pulse was single-user before 0.9.0, so every existing account is the installation administrator.
UPDATE users
SET role = 'administrator';
