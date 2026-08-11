# Pulse

Pulse is a small, framework-free PHP application for personal emergency check-ins. It lets a user configure monitors, trusted contacts, and recipient-specific documents in preparation for a later staged notification and delivery workflow.

Version **0.4.2** completes monitor configuration. It adds a tabbed monitor editor, default and recipient-specific messages, editable text documents, document-recipient assignment, owner-confirmed contact address checks, and clearer monitor states. Automated reminders, notification delivery, recipient access, MFA, and document encryption are not implemented yet.

> **Important:** Pulse 0.4.2 stores uploaded files outside the public web root, but files, messages, and editable text documents are not encrypted at rest. Do not treat this release as the finished secure vault for highly sensitive material. Secure storage is planned for a later release.

## Requirements

- PHP 8.4 or 8.5
- PDO MySQL and Fileinfo PHP extensions
- MySQL 8+ or MariaDB 10.6+
- Apache with `mod_rewrite`, or equivalent routing on another web server
- HTTPS in production

Composer is used for development tools. The application retains a small PSR-4 fallback autoloader, so a production deployment does not require the `vendor/` directory.

## Installation

1. Extract the complete source archive into the Pulse project directory.
2. Copy `.env.example` to `.env` and enter the real URL and database credentials.
3. Create an empty database.
4. Ensure `storage/logs`, `storage/tmp`, and `storage/uploads` are writable by PHP but not publicly accessible.
5. Configure the site’s document root as the project’s `public/` directory.
6. Serve the application exclusively over HTTPS.
7. Open Pulse in a browser. The first request creates and migrates the database automatically.

The public entry point finds the application through this explicit chain:

```text
Pulse/public/index.php
        │ dirname(__DIR__)
        ▼
Pulse/bootstrap.php
        ├── app/
        ├── config/
        └── storage/
```

`public/index.php` does not search its own directory for the application. Its `dirname(__DIR__)` expression resolves the parent Pulse directory, then loads the root `bootstrap.php`.

## Upgrading to 0.4.2

1. Back up the database and `storage/` directory.
2. Extract the 0.4.2 source ZIP over the existing Pulse project directory.
3. When upgrading from 0.2.9, create `.env` from `.env.example`; do not copy credentials back into `config/database.php`.
4. Rotate the former database and application passwords if this was not already done.
5. Confirm that the server document root is `public/`.
6. Open Pulse in a browser. Pending migrations are applied automatically before the request is handled.
7. Delete any old `__MACOSX`, `.DS_Store`, and `__pycache__` material left by earlier archives.

The migration runner detects a pre-migration Pulse database, records the consolidated legacy baseline, and applies only the required migrations. Existing user, contact, monitor, and document data is retained. A database-level advisory lock prevents concurrent requests from applying the same migration twice.

Existing contacts begin with an unchecked address state because Pulse cannot know whether you previously reviewed their email addresses. In a monitor editor, open **Recipients**, choose **Check address** beside the contact, review the address, tick the confirmation box, and save. This confirmation is local to your account; Pulse does not send the contact any message.

## 0.4 complete configuration

- The monitor editor is organized into Schedule, Recipients, Messages & documents, and Review & activation tabs.
- A monitor can define one default delivery subject and message.
- Each assigned recipient can optionally override the default message.
- Editable text documents can be created directly in Pulse and assigned to recipients.
- Uploaded files and text documents share the same recipient-assignment model.
- Contact email addresses require an explicit owner check and receive conservative common-domain typo warnings.
- Adding or checking a contact never sends an invitation, approval request, or verification email.
- Monitor state now distinguishes Checked in, Awaiting check-in, Overdue, Escalated, and Paused.
- The upload notification now displays the actual uploaded filename when its optional title is empty.

## 0.3 security foundation

- Credentials are read from environment variables or an ignored local `.env` file.
- Production debug output is disabled and errors receive opaque reference IDs.
- Session cookies use `Secure`, `HttpOnly`, and `SameSite`, with idle, absolute, and regeneration timeouts.
- Every POST action uses a random, session-bound CSRF token.
- Logout and language changes are POST actions.
- External and malformed redirect targets are rejected.
- A strict Content Security Policy and additional browser security headers are applied centrally.
- Login attempts are throttled by opaque account and network hashes; plaintext emails and IP addresses are not stored in the throttle table.
- Uploaded files have configurable size and MIME allowlists, random extensionless storage names, and restrictive file permissions.
- Document files are removed when documents or monitors are deleted.
- Monitor edits preserve retained `monitor_contact` rows instead of deleting and recreating them.
- Public password utility scripts from older builds are overwritten with disabled stubs and access is denied at the web-server level.
- Public health output reveals only `{"status":"ok"}`; detailed readiness checks require authentication.

See [docs/SECURITY.md](docs/SECURITY.md) for the remaining limitations and planned secure document design.

## Development

Install the development tools:

```bash
composer install
```

Run the checks:

```bash
composer test
composer analyse
composer check-style
```

The MySQL integration test runs only when `PULSE_TEST_DB_DATABASE` names a dedicated database ending in `_test`. This guard prevents accidental modification of a normal database.

To expose the development-only **Force due now** action, use a non-production environment and set:

```dotenv
PULSE_ENV=development
PULSE_DEBUG=true
PULSE_COOKIE_SECURE=false
PULSE_ALLOW_FORCE_DUE=true
```

Never enable this action in production.

## Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [User guide](docs/USER_GUIDE.md)
- [Security model](docs/SECURITY.md)
- [Upgrade guide](docs/UPGRADING.md)
- [Changelog](CHANGELOG.md)

## License

Pulse is released under the MIT License. See [docs/LICENSE](docs/LICENSE).
