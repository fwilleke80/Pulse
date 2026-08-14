-- Pulse 0.9.5 pre-1.0 legacy schema cleanup.
-- These tables were created by the original foundation schema but have never been used by the runtime.
DROP TABLE IF EXISTS access_tokens;
DROP TABLE IF EXISTS app_settings;
