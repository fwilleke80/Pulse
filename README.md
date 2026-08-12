# Pulse

Pulse is a small, framework-free PHP application for personal emergency check-ins. It lets a user configure monitors, recipients, optional safety contacts, messages, and recipient-specific documents.

Version **0.7.1** adds actual recipient notification emails, an optional per-monitor safety-contact gate, and dedicated recipient configuration pages with exact email preview, compact document assignment, and immutable delivery history. Recipient email subjects and bodies are sent exactly as configured; Pulse does not prepend or append hidden explanatory text. Recipient emails still contain no document content or document-access link. Documents remain gated for a later secure portal release.

> **Important:** Pulse 0.7.1 can send real, irreversible email to recipients and safety contacts. Test configuration with non-sensitive addresses first. Uploaded files remain outside the public web root, but files, messages, and editable text documents are not encrypted at rest. Do not treat this release as the finished secure vault for highly sensitive material.

## Requirements

- PHP 8.4 or 8.5
- PDO MySQL, Fileinfo, and OpenSSL PHP extensions
- MySQL 8+ or MariaDB 10.6+
- Apache with `mod_rewrite`, or equivalent routing on another web server
- HTTPS in production

Composer is used for development tools. The application retains a small PSR-4 fallback autoloader, so a production deployment does not require the `vendor/` directory.

## Installation

1. Extract the complete source archive locally into the Pulse project directory.
2. Before uploading any PHP files, run `python3 tools/write_version.py`. This generates `config/version.php`; upload that generated file with the application. In a tagged Git checkout the script derives the version from Git, while a packaged archive retains its packaged version. Set `PULSE_VERSION=0.7.1` for an explicit release value when needed.
3. Copy `.env.example` to `.env` and enter the real URL and database credentials.
4. Create an empty database.
5. Upload the project and ensure `storage/logs`, `storage/tmp`, and `storage/uploads` are writable by PHP but not publicly accessible.
6. Configure the site’s document root as the project’s `public/` directory.
7. Serve the application exclusively over HTTPS.
8. Open Pulse in a browser. The first request creates and migrates the database automatically.
9. Configure SMTP, enable mail, and send a test from **Profile → Notifications**.
10. Install either the web-cron URL or command-line cron job shown below.

If `config/version.php` is missing, Pulse remains operational and displays **version unavailable** instead of failing. Generate the file before deployment so asset cache keys and the displayed release are accurate.

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

## Upgrading to 0.7.1

1. Back up the database and `storage/` directory.
2. Extract the 0.7.1 source ZIP into a local working directory.
3. Run `python3 tools/write_version.py` **before uploading the PHP files**, and include the generated `config/version.php` in the upload.
4. Upload the source over the existing Pulse project directory without replacing `.env` or `storage/`.
5. When upgrading from 0.2.9, create `.env` from `.env.example`; do not copy credentials back into `config/database.php`.
6. Rotate the former database and application passwords if this was not already done.
7. Confirm that the server document root is `public/`.
8. Open Pulse in a browser. Pending migrations are applied automatically before the request is handled.
9. Review every recipient message and every safety-contact selection before relying on a monitor.
10. Delete any old `__MACOSX`, `.DS_Store`, and `__pycache__` material left by earlier archives.

The migration runner detects a pre-migration Pulse database, records the consolidated legacy baseline, and applies only the required migrations. Existing user, contact, monitor, and document data is retained. A database-level advisory lock prevents concurrent requests from applying the same migration twice.

Existing contacts begin with an unchecked address state because Pulse cannot know whether you previously reviewed their email addresses. Open **Contacts**, edit the contact, review the address, tick the confirmation box, and save. This confirmation is local to your account; the address-check action itself sends no message.

Migration `005_check_in_lifecycle.sql` converts existing lightweight timing data into persisted cycles. Active monitors receive a scheduled or awaiting cycle based on their existing next due time; paused monitors remain paused. Existing duplicate open cycles, if any, are reduced to one current cycle without deleting their history.

Migration `006_notification_infrastructure.sql` upgrades the provisional mail tables with immutable idempotency keys, retry counters, queue states, worker leases, attempt logs, and cycle reminder timestamps. Existing provisional queue rows are retained and assigned legacy idempotency keys.

Migration `007_recipient_notification_languages.sql` adds an optional notification language to the owner profile and every contact. Existing null values use `PULSE_DEFAULT_LOCALE` until explicitly saved.

Migration `008_immediate_due_notifications.sql` records successful initial due notices separately from follow-up reminders. Existing cycles that already delivered a 0.6.2 reminder are marked as already notified so an upgrade does not send them a duplicate late due notice.

Migration `009_recipient_escalation.sql` adds per-monitor escalation policy, optional safety-contact assignments and requests, hashed safety-link tokens, immutable recipient releases and deliveries, and queue links for the new mail types. Existing monitors default to **Direct recipient notification**, so review active configurations promptly after upgrading.

## 0.7 recipient escalation

- Each monitor chooses **Direct recipient notification** or an optional **Safety-contact gate**.
- Direct monitors stage recipient emails after the owner due notice, response window, and all configured owner reminders have completed without a check-in.
- Safety-gated monitors first email one or more checked safety contacts. A configurable confirmation quorum can postpone the monitor; a safety contact can never accelerate recipient delivery.
- Merely opening a safety link changes nothing. The contact must submit an explicit CSRF-protected response. Tokens are random, purpose-bound, resolved through stored hashes, and expire. The raw link exists in the queued email while retries are possible and is redacted from the queue after success or cancellation.
- Recipient address, language, subject, and body are snapshotted before queueing. Later edits affect only future releases.
- A monitor becomes **Escalated** only after SMTP accepts the first recipient message. If every recipient attempt fails, it remains truthfully **Overdue** with a visible delivery warning.
- Recipient pages separate reusable contact details from monitor-specific email text, compact document assignments, exact preview, and delivery history.
- Documents are still inaccessible to recipients in 0.7.1. Assignments are configuration for a future secure portal; recipient delivery never attaches document content or adds a document-access link.
- The scheduler and worker remain idempotent, leased, retryable, and safe to overlap.

## 0.6 notification infrastructure

- Pulse sends an immediate check-in due notice, followed by any configured reminders, only to the monitor owner's profile email address.
- Messages are snapshotted into a durable database queue before delivery.
- Concurrent workers claim jobs with row locks and expiring leases; abandoned jobs are recovered automatically.
- Failed attempts use configurable retry delays and stop after a bounded number of attempts.
- A due notice or reminder counts as sent only after SMTP accepts the complete message.
- Permanently failed owner notifications leave the monitor truthfully at **Awaiting check-in** and add a visible delivery-failure warning. They can be requeued from **Profile → Notifications**.
- Test notifications use the same queue, SMTP transport, retry state, and delivery log as real reminders.
- Owner reminders and tests use the owner recipient's stored notification language, independently of the active interface language.
- Each contact stores a separate language used for safety-contact and recipient mail.
- Recipient messages are mailed after the configured escalation process; documents are not attached or accessible.

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

- The monitor editor is organized into Schedule, Recipients, Messages & documents, Safety & escalation, and Review & activation tabs.
- A monitor can define one default delivery subject and message.
- Each assigned recipient can optionally override the default email subject and body. The configured subject and body are the exact recipient email; Pulse does not add a hidden wrapper.
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

To expose the lifecycle test actions, use a non-production environment and set:

```dotenv
PULSE_ENV=development
PULSE_DEBUG=true
PULSE_COOKIE_SECURE=false
```

For a checked-in monitor, **Force due now** opens the normal awaiting cycle. The same row then offers **Send due notification now**, which queues and immediately attempts the real SMTP message without waiting for cron. After that notice is recorded, **Send recipient notification now** can deliberately bypass the remaining owner and safety waiting periods, snapshot the real recipient messages, and attempt real delivery. It requires an explicit confirmation and exists only in non-production debug mode. Pulse ignores `PULSE_DEBUG=true` when `PULSE_ENV=production`.

## Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [User guide](docs/USER_GUIDE.md)
- [Monitor seriousness tutorial](docs/MONITOR_TUTORIAL.md)
- [Security model](docs/SECURITY.md)
- [Upgrade guide](docs/UPGRADING.md)
- [Changelog](CHANGELOG.md)

## License

Pulse is released under the MIT License. See [docs/LICENSE](docs/LICENSE).
