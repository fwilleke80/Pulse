# Pulse

Pulse is a small, framework-free PHP application for personal emergency check-ins. It lets a user configure monitors, trusted contacts, and recipient-specific documents in preparation for a later staged notification and delivery workflow.

Version **0.6.3** sends an immediate owner notification when a monitor becomes due. The response window now starts after that visible due notice; configured reminders are follow-ups sent only after the window closes. Notification language remains recipient-specific, and recipient delivery itself remains inactive until the later secure-delivery milestone.

> **Important:** Pulse 0.6.3 stores uploaded files outside the public web root, but files, messages, and editable text documents are not encrypted at rest. Do not treat this release as the finished secure vault for highly sensitive material. Secure storage is planned for a later release.

## Requirements

- PHP 8.4 or 8.5
- PDO MySQL, Fileinfo, and OpenSSL PHP extensions
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
8. Configure SMTP, enable mail, and send a test from **Profile → Notifications**.
9. Install either the web-cron URL or command-line cron job shown below.

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

The separate `public/cron/cron.php` endpoint loads the same application root for protected background notification runs.

## Upgrading to 0.6.3

1. Back up the database and `storage/` directory.
2. Extract the 0.6.3 source ZIP over the existing Pulse project directory.
3. When upgrading from 0.2.9, create `.env` from `.env.example`; do not copy credentials back into `config/database.php`.
4. Rotate the former database and application passwords if this was not already done.
5. Confirm that the server document root is `public/`.
6. Open Pulse in a browser. Pending migrations are applied automatically before the request is handled.
7. Delete any old `__MACOSX`, `.DS_Store`, and `__pycache__` material left by earlier archives.

The migration runner detects a pre-migration Pulse database, records the consolidated legacy baseline, and applies only the required migrations. Existing user, contact, monitor, and document data is retained. A database-level advisory lock prevents concurrent requests from applying the same migration twice.

Existing contacts begin with an unchecked address state because Pulse cannot know whether you previously reviewed their email addresses. In a monitor editor, open **Recipients**, choose **Check address** beside the contact, review the address, tick the confirmation box, and save. This confirmation is local to your account; Pulse does not send the contact any message.

Migration `005_check_in_lifecycle.sql` converts existing lightweight timing data into persisted cycles. Active monitors receive a scheduled or awaiting cycle based on their existing next due time; paused monitors remain paused. Existing duplicate open cycles, if any, are reduced to one current cycle without deleting their history.

Migration `006_notification_infrastructure.sql` upgrades the provisional mail tables with immutable idempotency keys, retry counters, queue states, worker leases, attempt logs, and cycle reminder timestamps. Existing provisional queue rows are retained and assigned legacy idempotency keys.

Migration `007_recipient_notification_languages.sql` adds an optional notification language to the owner profile and every contact. Existing null values use `PULSE_DEFAULT_LOCALE` until explicitly saved.

Migration `008_immediate_due_notifications.sql` records successful initial due notices separately from follow-up reminders. Existing cycles that already delivered a 0.6.2 reminder are marked as already notified so an upgrade does not send them a duplicate late due notice.

## 0.6 notification infrastructure

- Pulse sends an immediate check-in due notice, followed by any configured reminders, only to the monitor owner's profile email address.
- Messages are snapshotted into a durable database queue before delivery.
- Concurrent workers claim jobs with row locks and expiring leases; abandoned jobs are recovered automatically.
- Failed attempts use configurable retry delays and stop after a bounded number of attempts.
- A due notice or reminder counts as sent only after SMTP accepts the complete message.
- Permanently failed owner notifications leave the monitor truthfully at **Awaiting check-in** and add a visible delivery-failure warning. They can be requeued from **Profile → Notifications**.
- Test notifications use the same queue, SMTP transport, retry state, and delivery log as real reminders.
- Owner reminders and tests use the owner recipient's stored notification language, independently of the active interface language.
- Each contact stores a separate language for the later recipient-delivery workflow.
- Recipient messages and documents are not mailed in this release.

Configure `.env` using the `PULSE_SMTP_*` and `PULSE_MAIL_*` examples:

```dotenv
PULSE_MAIL_ENABLED=true
PULSE_SMTP_HOST=smtp.example.com
PULSE_SMTP_PORT=587
PULSE_SMTP_ENCRYPTION=starttls
PULSE_SMTP_USERNAME=pulse@example.com
PULSE_SMTP_PASSWORD=replace-with-the-smtp-password
PULSE_MAIL_FROM_ADDRESS=pulse@example.com
PULSE_MAIL_FROM_NAME=Pulse
```

Replace the example values with the SMTP server, account, password, and permitted sender address supplied by the mail provider. Use `tls` with the provider's implicit-TLS port, commonly 465, instead of `starttls` when required. Production does not permit unencrypted SMTP. Process-level environment variables take precedence over values in `.env`.

After saving `.env`, choose your notification language under **Profile data**, reload **Profile → Notifications**, and send a successful test. Actual scheduled reminders additionally require one scheduler/worker tick every minute.

For URL-only hosting cron services, generate a random token of at least 32 characters and add it to `.env`:

```dotenv
PULSE_CRON_TOKEN=replace-with-a-long-random-web-cron-token
```

Then configure the hosting service to request this URL once per minute, substituting the real HTTPS origin and token:

```text
https://pulse.example.com/cron/cron.php?token=replace-with-a-long-random-web-cron-token
```

The endpoint performs only the combined notification run. It accepts no command-line or URL operational parameters and uses `PULSE_MAIL_WORKER_BATCH_SIZE` for the bounded queue batch. A correct call returns `OK`.

If command-line cron is available, it remains an equivalent alternative:

```cron
* * * * * cd /path/to/pulse && /usr/bin/php tools/pulse.php notifications:run --limit=25 >/dev/null 2>&1
```

The command is safe to overlap: workers skip rows leased by another process. Useful operator commands are:

```bash
php tools/pulse.php notifications:schedule
php tools/pulse.php mail:work --limit=25
php tools/pulse.php mail:test --user-id=1
php tools/pulse.php mail:retry-failed --limit=100
```

## 0.5 reliable check-in lifecycle

- One **Check in now** action confirms every active monitor in a single transaction; paused monitors remain untouched.
- Checking in early is allowed. Each active monitor restarts its own configured interval from the shared confirmation time.
- Every monitor has a persisted current cycle with an explicit state and UTC deadlines.
- Opening the dashboard or monitor pages advances a scheduled cycle to **Awaiting check-in** when its due time arrives.
- **Overdue** is never inferred from elapsed time alone. The notification worker must record that the initial due notice and all configured follow-up reminders were actually sent.
- **Escalated** is reserved for the point when recipient delivery has actually begun.
- **Pause** cancels the current cycle. **Resume** counts as a fresh confirmation and creates a new interval from that moment.
- The dashboard shows all monitor states, next due times, operational actions, and the latest 10 lifecycle entries, with a link to the complete paginated history.

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
