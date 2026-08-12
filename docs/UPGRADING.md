# Upgrading Pulse

## Upgrade from 0.2.9–0.6.4 to 0.7.0

### Before extraction

1. Put the application into maintenance mode if it is currently reachable.
2. Back up the database.
3. Back up `storage/uploads` separately.
4. Record the current hosting document-root configuration.

### Generate the version before upload

Run this in the extracted local source tree **before uploading any PHP files to the server**:

```bash
python3 tools/write_version.py
```

The command generates `config/version.php`, which must be uploaded with the application. In a Git checkout it uses `git describe`; in a packaged source archive without `.git`, it retains the packaged value already present in `config/version.php`. To create an explicit release value, run:

```bash
PULSE_VERSION=0.7.0 python3 tools/write_version.py
```

If `config/version.php` is accidentally absent, Pulse does not fail: the interface displays **version unavailable** and uses an `unversioned` asset cache key. This fallback is operational protection, not a substitute for running the generator before deployment.

### Install the source

Extract `Pulse_0.7.0_source.zip` locally, run the version generator there, then upload the extracted tree over the Pulse project directory. The archive paths begin with `app/`, `config/`, `public/`, and the other project-root entries; it does not add an extra `Pulse/` directory. Ensure the generated `config/version.php` is included in the uploaded files.

Extraction overwrites the old public password utility files with inert 404 stubs. You may delete `public/secret0410` entirely after extraction.

### Configure secrets

When upgrading from 0.2.9, copy `.env.example` to `.env` and enter fresh values. Do not restore the old credential-bearing `config/database.php`; Pulse 0.3.x reads `PULSE_DB_*` values instead.

When upgrading from 0.3.x, retain the existing `.env`. Extracting the archive does not overwrite it because `.env` is deliberately not included.

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

For an existing Pulse database without `schema_migrations`, the runner detects the `users` table and records migrations 001 and 002 as the legacy baseline. It then applies the security-foundation and complete-configuration migrations. For an existing 0.3.1 database, `004_complete_configuration.sql` adds the owner address-check timestamp, default monitor message fields, and the one-message-per-monitor-contact constraint.

Migration `005_check_in_lifecycle.sql` then:

- adds explicit pause timestamps
- expands check-cycle states and adds UTC due/response timestamps plus reminder snapshots
- converts legacy pending cycles to awaiting cycles
- cancels open cycles belonging to paused monitors
- retains only the newest current cycle if legacy data contains duplicates
- creates one scheduled or awaiting cycle for every active monitor that lacks one

No existing monitor, contact, document, or completed cycle history is deleted.

Migration `006_notification_infrastructure.sql` upgrades the provisional `mail_queue` and `mail_log` tables with idempotency keys, retry counters, delivery states, worker leases, attempt history, and supporting indexes. It also records the last successfully sent reminder on each check cycle. Existing provisional queue rows are preserved.

Migration `007_recipient_notification_languages.sql` adds nullable notification-language fields to the user and contact tables. Existing recipients use `PULSE_DEFAULT_LOCALE` until their own setting is saved; no language is inferred from a browser session.

Migration `008_immediate_due_notifications.sql` adds a separate successful-delivery timestamp for the initial check-in due notice. An awaiting cycle that has not yet delivered any 0.6.2 reminder receives the new due notice on the next notification run. A cycle that already delivered a reminder is marked as already notified during migration, preventing a duplicate late due notice.

Migration `009_recipient_escalation.sql` then:

- adds a direct or safety-contact escalation policy and safety timing fields to every monitor
- extends cycle snapshots with the policy, safety timing, gate progress, and `safety_pending` state
- adds per-monitor safety-contact assignments
- stores immutable safety requests and multiple hashed, expiring link tokens
- stores immutable recipient releases and per-recipient delivery snapshots
- links safety and recipient jobs to the durable mail queue

Existing monitors use **Direct recipient notification** by default. This is intentionally functional: after their owner-notification phase completes, eligible monitors can now send actual recipient email. Review all active monitors, recipients, checked addresses, and messages immediately after upgrade. Pause a monitor first if its configuration is not ready.

Existing contacts receive a null `email_checked_at` value. This is intentional: Pulse cannot infer that an address was reviewed before the feature existed. Reviewing a contact updates the local timestamp without sending anything to that contact.

Actual upgrades are protected by a database advisory lock. If two requests arrive immediately after extraction, one applies the migrations and the other waits, rechecks the result, and continues without applying them twice.

Do not import `database/schema.sql` over an existing database.

### Web server

Confirm that the website document root is the extracted project’s `public/` directory. For Apache, `public/.htaccess` handles application routes. The root and storage access-denial files are defense in depth, not substitutes for the correct document root.

### Configure SMTP and cron

Copy the new `PULSE_SMTP_*` and `PULSE_MAIL_*` entries from `.env.example` into the existing `.env`. Use `starttls` (usually port 587) or implicit `tls` (usually port 465); production rejects unencrypted SMTP.

Set `PULSE_MAIL_ENABLED=true` only after entering the SMTP host, sender address, and any required username and password. Process-level environment variables override `.env`; update the hosting environment as well if it already defines `PULSE_MAIL_ENABLED=false`.

After enabling mail, choose your own language under **Profile data**, then send a test from **Profile → Notifications**. Only after that succeeds, install one once-per-minute notification trigger.

For a hosting service that accepts only a URL, add a random token of at least 32 characters to `.env`:

```dotenv
PULSE_CRON_TOKEN=replace-with-a-long-random-web-cron-token
```

Configure the service to call:

```text
https://pulse.example.com/cron/cron.php?token=replace-with-a-long-random-web-cron-token
```

The endpoint performs only one complete notification run and takes its queue limit from `PULSE_MAIL_WORKER_BATCH_SIZE`. A successful request returns `OK`. Keep the complete URL secret because hosting access logs may contain it.

If command-line cron is available, this remains an equivalent alternative:

```cron
* * * * * cd /path/to/pulse && /usr/bin/php tools/pulse.php notifications:run --limit=25 >/dev/null 2>&1
```

Overlapping invocations are safe because queue jobs use database row locks and expiring worker leases.

### Verification

1. Open `/health` and verify the minimal `ok` response.
2. Sign in and open `/health/readiness`.
3. Verify contacts and monitors are present.
4. Open each monitor editor tab and confirm existing contacts and documents are present.
5. Review an existing contact, confirm its address, and verify its checked status appears in the monitor editor and dedicated recipient page.
6. Save a default message, open one dedicated recipient page, save a personal override, and review the localized preview.
7. Create, edit, assign, and delete a non-sensitive text document.
8. Upload a non-sensitive test PDF without a title and verify the success message contains its filename.
9. Download and delete that PDF, then confirm its storage file is removed.
10. Verify the dashboard shows every monitor and one global **Check in now** action.
11. Check in and confirm that every active monitor receives the same last-confirmed time but keeps its own next-due interval.
12. Pause one monitor and verify its next due date becomes suspended and global check-in leaves it unchanged.
13. Resume it and verify a fresh interval begins from the resume time.
14. In a development environment, set `PULSE_DEBUG=true`, use **Force due now**, and verify the selected cycle changes to **Awaiting check-in**.
15. Use **Send due notification now** and verify the owner immediately receives the check-in due notice through the real queue and SMTP worker.
16. On a disposable monitor, choose **Safety-contact gate**, select only checked contacts, and verify the confirmation quorum does not exceed the number selected.
17. Let a non-sensitive safety request run normally. Verify that opening its link changes nothing and that only an explicit submitted confirmation postpones the monitor.
18. Verify a safety contact cannot access recipient messages or documents and cannot accelerate release.
19. In development only, read the warning and use **Send recipient notification now** with non-sensitive test recipients. Verify each dedicated recipient page records its immutable delivery state.
20. Confirm the monitor changes to **Escalated** only after the first recipient SMTP success. With an intentionally failed test delivery, confirm it remains **Overdue** and shows a failure.
21. Confirm a recipient email contains the configured message but no attachment, document content, or document-access link.
22. Confirm recent lifecycle activity records check-ins, pauses, resumes, due changes, safety stages, and recipient delivery.
23. Save monitor settings from each of the five tabs and confirm Pulse returns to the same tab.
24. Configure SMTP and send a successful test from **Profile → Notifications**.
25. Set the owner and contact notification languages, change the footer language, and verify another test still uses the stored owner language.
26. Call the configured web-cron URL once and verify it returns `OK`, or run the equivalent command-line notification operation.
27. Temporarily move `config/version.php` out of the project, verify the footer says **version unavailable** without an application failure, then restore the generated file.
28. Confirm production pages do not show stack traces.

### Old archive debris

Earlier archives contained macOS and Python cache files. They are excluded from current releases, but extracting cannot delete files already present. Remove old `.DS_Store`, `__MACOSX`, and `__pycache__` entries from the project directory.
