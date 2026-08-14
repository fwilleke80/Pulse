# Pulse architecture

Pulse is a small framework-free PHP application. It uses a single public web directory, a front controller for normal application requests, a separate protected cron endpoint, service classes for the monitor lifecycle, and repositories for database access.

This document intentionally describes the main structure rather than every class.

## Project layout

```text
Pulse/
├── app/                 Application code
├── config/              Application configuration
├── database/            Schema and migrations
├── docs/                Documentation
├── public/              Web-server document root
│   ├── assets/
│   ├── cron/cron.php    Protected web-cron endpoint
│   └── index.php        Front controller
├── storage/             Logs, temporary files, and private uploads
├── tests/               Development tests
├── tools/               Command-line tools
└── bootstrap.php        Application bootstrap
```

Only `public/` is intended to be web-accessible.

With Apache, `public/.htaccess` rewrites application URLs to `public/index.php` while leaving real public files alone. Other servers can use equivalent front-controller routing.

## Normal request flow

```text
Browser request
    ↓
public/index.php
    ↓
Router
    ↓
Controller
    ↓
Service / Repository
    ↓
Database or private storage
    ↓
HTML, redirect, or download response
```

The front controller handles cross-cutting web concerns such as trusted-host validation, security headers, and CSRF checks for state-changing requests.

`bootstrap.php` constructs the application services and resolves the private project directories outside `public/`.

## Main application areas

### Core infrastructure

`app/Core` contains the small amount of framework-like infrastructure Pulse needs, including:

- environment/configuration loading
- database access
- routing and requests
- sessions and CSRF protection
- security headers
- error handling and logging
- database migrations
- translation
- email-address validation
- cron authentication

Pulse retains its own PSR-4 autoloading fallback, so the production application does not require a Composer `vendor/` directory.

### Controllers

Controllers receive routed requests, enforce authentication and ownership where required, validate the requested action, call the application services, and render or redirect.

Public safety-contact pages are deliberately separated from normal signed-in owner pages.

### Services

The main service responsibilities are:

- authentication and login throttling
- monitor scheduling and check-ins
- pause and resume
- notification scheduling
- mail queue processing
- recipient and safety-contact escalation
- document upload and private file operations
- test notifications

The monitor lifecycle is kept in service/state-machine code instead of being spread across page controllers.

### Repositories

Repositories contain the SQL used to load and update Pulse data. Ownership-sensitive operations include the current user or parent monitor in their queries so one account cannot access another account's data merely by changing an ID in a URL.

## Domain model

At a high level:

```text
User
├── Contacts
└── Monitors
    ├── Check cycles
    │   ├── Safety-contact requests
    │   └── Recipient releases
    ├── Safety-contact assignments
    ├── Recipients
    │   └── Recipient-specific messages
    ├── Default message
    └── Documents
        └── Recipient assignments
```

A contact is reusable. A monitor recipient is the role that contact has within one particular monitor.

Documents belong to monitors and can be assigned to several recipients without storing duplicate copies.

## Monitor lifecycle

Each active monitor has one current check cycle. A successful check-in closes the current cycle and creates a new scheduled cycle with a fresh due time.

The user-facing states are:

```text
Checked in
    ↓ due time
Awaiting check-in
    ↓ owner phase complete
    ├── Direct policy ─────────────→ Overdue
    └── Safety policy → Awaiting safety contact
                           ├── enough confirmations → new scheduled cycle
                           └── gate expires ────────→ Overdue

Overdue
    ↓ first successful recipient delivery
Escalated
```

A check-in can close an open cycle at any stage and start a new one. A late check-in cannot undo email that has already been sent.

Pausing cancels the current open cycle. Resuming starts a fresh scheduled cycle rather than reviving an old deadline.

All stored timestamps used for lifecycle decisions are UTC. The interface converts them to the configured display timezone.

## Notification processing

The notification runner can be started in either of two ways:

- the protected web endpoint `public/cron/cron.php`
- the command-line `notifications:run` command

Both perform the same basic work:

1. synchronize monitor cycles and create any notifications that have become eligible
2. claim and deliver a bounded batch of queued mail

Queue rows contain immutable delivery snapshots so later edits do not change an already queued email.

Workers claim jobs with database row locks and expiring leases. SMTP delivery happens outside the claim transaction; afterward Pulse records success, retry, or permanent failure and updates the linked monitor state.

The first successful final-recipient delivery changes an overdue cycle to **Escalated**.

## Safety-contact responses

A safety-contact email contains a random response token. Pulse stores a hash of that token and validates its request and expiry when the link is used.

Opening the link only displays the safety page. The actual confirmation or inability-to-confirm response is a separate state-changing request. This avoids accidental confirmation by email scanners and previews.

If the configured confirmation quorum is reached, Pulse closes that cycle and schedules a new one. A safety contact cannot accelerate final recipient delivery.

Safety-contact invitation and reminder text can be customized per monitor. When a safety gate starts, the configured templates are copied into each `safety_contact_requests` row so subsequent reminders for that request use a stable snapshot. Blank custom templates fall back to Pulse's localized built-in copy.

## Configuration

Runtime settings come from process environment variables and the root `.env` file. Process environment variables take precedence.

Credentials are not stored in committed configuration files.

`config/version.php` is generated before deployment by:

```bash
python3 tools/write_version.py
```

A missing version file does not stop the application; the UI displays **version unavailable**.

## Database migrations

Pulse keeps ordered SQL migrations in `database/`.

At application startup, Pulse checks which migrations have already been applied and applies any pending ones before handling the request. Migration work is protected so simultaneous first requests do not intentionally apply the same migration twice.

Normal installation and upgrading therefore do not require a separate migration command.

Released migration files should not be edited after publication; schema changes should be added as new migrations.

## Document storage

Uploaded document content is:

- checked for size and MIME type
- given an internal random storage name
- stored under private `storage/uploads` space outside `public/`
- downloaded only through an authenticated ownership-checked controller

Editable text documents and message text are stored in the database. Uploaded-file records also store an editable display title and optional description separately from the immutable internal storage basename.

This private storage model prevents direct public file access, but it is not encryption. Pulse 0.8.3 provides authenticated recipient document delivery and a visual recipient download page; encrypted document/message storage remains later work.


## Recipient portal and document delivery

Each staged `recipient_release_deliveries` row receives an independent 256-bit random portal token. Only its SHA-256 hash is stored with the delivery; the raw token exists in the outgoing recipient email until that queue item is delivered, then the queue body is redacted. Portal availability is snapshotted per delivery and its expiry clock begins only when the final recipient notification is successfully accepted for delivery.

`recipient_portal_codes` stores short-lived authentication challenges. Codes are human-readable random values, valid for 30 minutes and one use, while only `password_hash()` output is persisted. Requesting a replacement invalidates earlier unused codes. Successful verification establishes a session entry scoped to the hashed portal token. Every authenticated portal request still revalidates the underlying delivery, so owner revocation or automatic expiry immediately makes an existing browser session unusable.

The public pre-authentication portal intentionally does not reveal the configured recipient email address, document metadata, or document content. After code verification, Pulse resolves the immutable `recipient_delivery_documents` snapshot for that delivery. Every document request rechecks both the active delivery token and the matching recipient session before streaming content. Text content and recipient-facing metadata are snapshotted at release; uploaded files remain in private storage and are referenced by immutable stored basenames.

The authenticated presentation layer uses reusable document cards and does not expose internal monitor-management controls. An authorization-checked inline-view endpoint is limited to passive MIME types (PDF, plain text, and common raster images); active or unknown formats remain attachment-only. Image previews use that same endpoint and therefore require the same recipient session as explicit downloads.

Bulk download is implemented by `RecipientPortalArchiveBuilder` as a direct store-only ZIP stream. It maintains only central-directory metadata in memory, reads file payloads in bounded chunks, and emits ZIP64 structures when classic ZIP count, size, or offset limits are exceeded. The implementation does not require the PHP `zip` extension and does not create a second full-size temporary archive. Long-running document responses release the PHP session lock before payload streaming so one recipient download does not serialize every other portal request from that browser.

## Localized monitor mail templates

Monitor-wide recipient, safety-invitation, and safety-reminder custom text is stored in `monitor_mail_templates`, keyed by monitor, template type, and locale. A contact's stored Pulse interface language selects the matching template. Recipient-specific messages remain attached to one monitor-contact assignment and therefore do not need parallel language variants. Safety request rows snapshot the already-selected language-specific subject/body when a gate starts, and recipient releases snapshot the final composed mail when staged. Recipient portal copy is stored separately: `monitor_portal_templates` holds language-specific monitor defaults for the portal message and download-page introduction, while `contact_portal_messages` holds optional per-recipient overrides. The resolved portal message/introduction are snapshotted into each `recipient_release_deliveries` row so later edits cannot rewrite an already released portal.

## Language discovery

`LanguageCatalog` scans `app/Lang/*.php` at startup and treats the discovered locale filenames as the supported-language set. Each language file declares its native display label in `_language.name`. The discovered set drives UI language switching, stored contact/profile language validation, and monitor mail-template tabs. `Translator` falls back to English for missing keys, so an incomplete additional language remains functional while it is translated.

Safety-contact requests snapshot their contact locale when the gate starts. Newly generated safety URLs also include that locale. A manual language switch on the public confirmation page is stored as a token-scoped session override, so it affects only that safety request and does not overwrite another safety request or the broader Pulse UI session language.
