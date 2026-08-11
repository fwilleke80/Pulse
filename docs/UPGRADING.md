# Upgrading Pulse

## Upgrade from 0.2.9 or 0.3.0 to 0.3.1

### Before extraction

1. Put the application into maintenance mode if it is currently reachable.
2. Back up the database.
3. Back up `storage/uploads` separately.
4. Record the current hosting document-root configuration.

### Install the source

Extract the complete `Pulse_0.3.1_source.zip` archive over the Pulse project directory. The archive paths begin with `app/`, `config/`, `public/`, and the other project-root entries; it does not add an extra `Pulse/` directory.

Extraction overwrites the old public password utility files with inert 404 stubs. You may delete `public/secret0410` entirely after extraction.

### Configure secrets

When upgrading from 0.2.9, copy `.env.example` to `.env` and enter fresh values. Do not restore the old credential-bearing `config/database.php`; Pulse 0.3.x reads `PULSE_DB_*` values instead.

When upgrading from 0.3.0, retain the existing `.env`. Extracting the archive does not overwrite it because `.env` is deliberately not included.

At minimum configure:

- `PULSE_BASE_URL`
- `PULSE_TRUSTED_HOSTS`
- `PULSE_DB_HOST`
- `PULSE_DB_DATABASE`
- `PULSE_DB_USERNAME`
- `PULSE_DB_PASSWORD`

Rotate credentials that appeared in the earlier repository or source archive.

### Apply the database migration

No command line is required. Open Pulse in a browser after extraction. Before handling that first request, Pulse checks the migration history and applies every pending migration automatically.

For an existing Pulse database without `schema_migrations`, the runner detects the `users` table and records migrations 001 and 002 as the legacy baseline. It then applies `003_security_foundation.sql`, which creates the login-throttle table and initializes missing monitor check-in timestamps. Existing 0.3.0 databases are already current, so 0.3.1 only verifies their migration checksums.

Actual upgrades are protected by a database advisory lock. If two requests arrive immediately after extraction, one applies the migrations and the other waits, rechecks the result, and continues without applying them twice.

Do not import `database/schema.sql` over an existing database.

### Web server

Confirm that the website document root is the extracted project’s `public/` directory. For Apache, `public/.htaccess` handles application routes. The root and storage access-denial files are defense in depth, not substitutes for the correct document root.

### Verification

1. Open `/health` and verify the minimal `ok` response.
2. Sign in and open `/health/readiness`.
3. Verify contacts and monitors are present.
4. Edit a monitor without changing its contacts and confirm document recipients remain selected.
5. Verify due monitors appear on the dashboard and can be checked in.
6. Upload and download a non-sensitive test PDF.
7. Delete that test document and confirm its storage file is removed.
8. Confirm production pages do not show stack traces.

### Old archive debris

Earlier archives contained macOS and Python cache files. They are excluded from 0.3.x, but extracting cannot delete files already present. Remove old `.DS_Store`, `__MACOSX`, and `__pycache__` entries from the project directory.
