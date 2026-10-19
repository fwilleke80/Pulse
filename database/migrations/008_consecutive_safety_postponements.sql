-- @brief Optional limit on consecutive safety-contact postponements; zero means unlimited.
ALTER TABLE monitors
  ADD COLUMN safety_postponement_limit INT UNSIGNED NOT NULL DEFAULT 0 AFTER safety_confirmation_days,
  ADD COLUMN consecutive_safety_postponements INT UNSIGNED NOT NULL DEFAULT 0 AFTER safety_postponement_limit;
