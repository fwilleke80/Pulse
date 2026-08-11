# Changelog

## 0.3.1 — 2026-08-11

### Deployment

- Apply pending database migrations automatically during application startup.
- Added a fast current-schema check so up-to-date requests do not take a migration lock or execute DDL.
- Serialize actual upgrades with a database-specific advisory lock to make simultaneous first requests safe.
- Removed terminal access from the installation and upgrade requirements.
- Retained `tools/migrate.php` as an optional developer and diagnostic helper.

## 0.3.0 — 2026-08-11

### Security

- Moved application and database configuration to environment-backed settings.
- Disabled production diagnostics and added opaque error references.
- Added hardened session cookies and idle, absolute, and periodic-regeneration limits.
- Added central CSRF validation to every POST action.
- Changed logout and language selection from GET to POST.
- Added local-only redirect validation.
- Added Content Security Policy, HSTS, framing, MIME-sniffing, referrer, permissions, and cross-origin headers.
- Added persistent account- and network-scoped login throttling without storing plaintext throttle subjects.
- Removed committed credential values and disabled the legacy public password utilities.
- Reduced public health output; added authenticated readiness diagnostics.
- Stopped logging contact names, email addresses, and document filenames.

### Documents

- Split document HTTP actions into `DocumentController`.
- Moved filesystem operations and upload policy into `DocumentService`.
- Added configurable upload size and MIME allowlists.
- Store new uploads under random `.bin` names with mode `0600` outside the public web root.
- Added traversal-safe stored-path resolution and hardened download headers.
- Remove physical documents when a document or its monitor is deleted.

### Monitors

- Fixed monitor-contact synchronization so unchanged assignments retain their IDs and dependent recipient data.
- Added due/scheduled/paused runtime status to the monitor overview.
- Added dashboard-visible due monitors and one-click manual check-in.
- Added an explicitly gated development-only **Force due now** action.
- Store and evaluate monitor scheduling timestamps in UTC.

### Foundation

- Added immutable typed request access and removed direct request-global access from controllers.
- Added a checksummed migration runner with automatic legacy baseline detection.
- Replaced the empty/fragile migrations with a reproducible schema history.
- Added Composer metadata, PHPUnit tests, PHPStan configuration, PHP-CS-Fixer configuration, and EditorConfig.
- Added Apache routing and access-denial rules.
- Added a responsive interface with automatic dark-mode colors.
- Added deployment, security, architecture, upgrade, and user documentation.
