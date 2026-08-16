# Pulse architecture

Pulse is a small framework-free PHP application. It uses one public web directory, a front controller for normal application requests, a protected web-cron endpoint, service classes for lifecycle behavior, repositories for database access, and private storage outside the web root.

This document describes the stable 1.0 structure and the main runtime flows rather than every class.

## Project layout

```text
Pulse/
├── app/                 Application code
│   ├── Controllers/
│   ├── Core/
│   ├── Installation/
│   ├── Lang/
│   ├── Mail/
│   ├── Repositories/
│   ├── Services/
│   └── Views/
├── config/              Environment-backed configuration and generated version
├── database/
│   ├── migrations/      Permanent numbered migrations
│   └── schema.sql       Current reference schema
├── docs/                Documentation
├── public/              Web-server document root
│   ├── assets/
│   ├── cron/cron.php    Protected web-cron endpoint
│   ├── index.php        Front controller
│   └── install.php      Temporary browser installer
├── storage/             Logs, temporary files, and private uploads
├── tests/               Unit/integration/source regression tests
├── tools/               Command-line tools
└── bootstrap.php        Dependency/bootstrap construction
```

Only `public/` is intended to be web-accessible.

Location-aware check-ins use `check_in_locations` as a one-to-one extension of the corresponding `monitor.checked_in` audit event and closed check cycle. Recording remains a per-monitor opt-in. When portal sharing is also enabled, escalation copies the configured bounded history into `recipient_release_locations`; authenticated deliveries read that release-level snapshot rather than live check-in data. Browser code performs one-shot geolocation and optional reverse geocoding. Recipient portals render the map shell inside the authenticated page but create no tile images until **Show locations on map** is selected. The browser then fetches only visible OpenStreetMap tiles and renders Pulse points, accuracy areas, and the chronological overlay locally without a third-party JavaScript dependency.

With Apache, `public/.htaccess` rewrites application URLs to `public/index.php` while leaving actual public files alone. Pulse 1.2's built-in exact-path router expects this public directory at the root of its host/virtual host rather than below a URL prefix.

Pulse retains a small PSR-4 fallback autoloader so the deployed application does not require a Composer `vendor/` directory.

## Normal request flow

```text
Browser request
    ↓
public/index.php
    ↓
Request + global security checks
    ↓
Router
    ↓
Controller
    ↓
Service / Repository
    ↓
Database or private storage
    ↓
HTML, redirect, or authorized download
```

The front controller handles trusted-host/security headers and applies the global CSRF check to state-changing POST requests before dispatch.

`bootstrap.php` loads configuration, validates it, creates the database/migration layer, initializes session/translation infrastructure, and constructs the repositories/services used by the controllers.

## Main application layers

### Core infrastructure

`app/Core` contains the small framework-like layer Pulse needs:

- environment and `.env` handling;
- fail-closed configuration validation;
- database access and migrations;
- request parsing and exact-path routing;
- sessions and CSRF;
- security headers;
- error handling/logging;
- translation/language discovery;
- email-address validation;
- safe local redirect validation;
- cron authentication.

### Controllers

Controllers are responsible for HTTP-level behavior:

- require authentication/administrator access where appropriate;
- enforce ownership of user resources;
- validate actions and input;
- invoke services/repositories;
- choose rendered views, redirects, or streamed responses.

Public safety-contact and recipient-portal controllers are deliberately separate from normal signed-in owner pages.

### Services

The main service responsibilities include:

- authentication and login throttling;
- monitor cycle initialization/check-ins;
- pause/resume/reset/archive lifecycle operations;
- notification scheduling;
- mail queue processing/retry;
- safety-contact and final-recipient escalation;
- document creation/upload/private file operations;
- recipient portal authentication/delivery;
- bulk ZIP streaming;
- test notifications.

Lifecycle rules are kept in service/state-machine code rather than being implemented independently in page controllers.

### Repositories

Repositories contain SQL for the application's data model. Ownership-sensitive queries include the user or parent monitor/delivery relationship so changing a numeric ID in a URL is not sufficient to cross an ownership boundary.

## Stable 1.0 data model

The initial 1.0 schema contains 23 application tables. At a high level:

```text
User
├── Contacts
├── Monitors
│   ├── localized recipient/safety mail templates
│   ├── localized portal templates
│   ├── current recipient assignments
│   │   ├── recipient-specific email override
│   │   ├── recipient-specific portal override
│   │   └── document assignments
│   ├── safety-contact assignments
│   ├── source documents
│   └── Check cycles
│       ├── Safety-contact requests
│       │   └── safety request tokens
│       └── Recipient releases
│           └── Recipient delivery per recipient
│               ├── snapshotted released documents
│               └── portal access codes
├── Mail queue / mail attempt log
└── Audit log

System status
└── latest successful combined cron run

Login attempts
└── throttling records
```

A **Contact** is reusable. A row in `monitor_contacts` is the role that contact has as a final recipient for one monitor.

Source documents belong to a monitor and can be assigned to multiple current recipients without duplicating their source record.

A final recipient **delivery** is different from the current monitor assignment. It is a historical snapshot created for a particular escalation.

## Monitor lifecycle

Each active monitor has one current open check cycle.

```text
Checked in
    ↓ due time
Awaiting check-in
    ↓ owner reminder phase complete
    ├── Direct policy ───────────────→ Overdue
    └── Safety policy → Awaiting safety contact
                           ├── quorum reached → new scheduled cycle
                           └── gate expires ───→ Overdue

Overdue
    ↓ first successful final-recipient delivery
Escalated
    ├── Reset and reactivate → new scheduled cycle
    └── Archive → read-only archived monitor

Paused
    └── Resume → new scheduled cycle
```

A normal check-in can close an open non-terminal cycle and create a fresh scheduled cycle. It cannot undo email that has already been sent.

**Escalated** is terminal for that cycle. Escalated monitors are intentionally excluded from global check-in and require an explicit Reset/reactivate or Archive decision.

Archiving freezes current monitor configuration but does not revoke historical recipient deliveries. Reset/reactivate starts a new current cycle and does not rewrite old deliveries.

Pausing closes the current active cycle. Resuming starts a new schedule rather than reviving an old deadline.

Lifecycle timestamps are stored in UTC. Views convert them to the configured display timezone.

## Notification scheduling and mail queue

The combined notification runner can be started by:

- `public/cron/cron.php` with the configured secret token; or
- `php tools/pulse.php notifications:run`.

A combined run performs two broad tasks:

1. synchronize monitor cycles and stage work that has become eligible;
2. claim and deliver a bounded batch of queued mail.

**Force due now** in debug mode only changes monitor timing/state. The next scheduler run is still responsible for discovering that due monitor and creating its due-notice queue job.

Queue jobs carry immutable delivery data needed for their outgoing message. Idempotency keys prevent the scheduler from intentionally creating duplicate logical jobs for the same lifecycle event.

Workers claim jobs transactionally using database row locks with `SKIP LOCKED`, mark them Processing, and assign expiring leases. SMTP delivery happens outside that claim transaction. Pulse then records Sent, Retrying, or terminal Failed state and updates the linked lifecycle state where required.

Overlapping cron workers can therefore process different queue rows concurrently without normally claiming the same job.

The SMTP transaction and database update cannot be atomic together. If SMTP accepts a message and the process dies before Pulse records the success, a later retry can still produce a duplicate delivery.

## Cron runtime status

Configuration remains in `.env`; operational runtime state does not.

The singleton `system_status` row stores the timestamp of the latest **fully successful combined cron run**. Both scheduler and queue processing must complete before the timestamp advances.

Administration uses this for **Last successful cron run** and its Never/Stale warning state.

## Safety-contact requests

When a safety gate begins, Pulse creates request records for the selected safety contacts and snapshots the language/template data required by that request.

Safety email links contain random purpose-specific tokens; Pulse stores hashes for lookup.

Opening the link is read-only. Confirming recent contact or explicitly saying the contact cannot confirm is a separate CSRF-protected state-changing POST. This avoids accidental confirmation by mail scanners or link previews.

Enough qualifying confirmations close the current cycle and create a new scheduled cycle. A **Cannot confirm** response does not accelerate escalation; the existing safety timetable continues.

If the safety deadline expires without the required quorum, the normal scheduler advances the cycle toward Overdue/final recipient release.

## Recipient release and portal delivery

When final escalation is staged, Pulse creates a release and one independent `recipient_release_deliveries` row per recipient.

The delivery snapshots include the information that should not later change implicitly:

- recipient identity, checked-address snapshot, and language;
- final composed notification subject/body;
- portal availability/expiry policy;
- optional recipient-specific personal portal message and monitor-wide introduction;
- authorized document set;
- recipient-facing document titles/descriptions;
- text-document content;
- private uploaded-file storage references.

Current monitor edits therefore affect future releases, not an existing delivery.

The owner may still deliberately edit limited presentation fields of an active delivery—portal copy and released document titles/descriptions—without changing authorization or payload contents.

### Portal token and access codes

Each delivery receives an independent random portal token. Only its SHA-256 hash is stored in the delivery record. The raw URL exists in the outgoing final-recipient email until the queue item is delivered, then the queue copy is redacted.

Opening the public portal does not expose document metadata/content. The recipient requests a one-time access code sent to the snapshotted address.

`recipient_portal_codes` stores only `password_hash()` output for codes. Codes are short-lived, one-use, request/attempt rate-limited, and scoped to one delivery.

Successful verification establishes a session entry keyed to that delivery/token. Every authenticated portal request revalidates the delivery, so expiry, owner revocation, or recipient-controlled closure immediately prevents further server-side access.

### Document delivery

The authenticated portal loads `recipient_delivery_documents`, not the current monitor assignment table.

Inline viewing is limited to passive MIME types. Other formats remain attachment-only.

`RecipientPortalArchiveBuilder` streams **Download all** as a store-only ZIP/ZIP64 archive. It reads file data in bounded chunks and does not build a second complete temporary archive before sending it.

Long-running document responses release the PHP session lock before streaming payloads so one large download does not block unrelated requests from the same browser session.

## Current documents versus released documents

Source documents and released delivery documents intentionally have different lifetimes.

Deleting a current source text document or removing a current recipient assignment must not mutate an already released snapshot. Uploaded private files that remain referenced by released deliveries are retained for those deliveries.

A monitor that has recipient delivery history cannot be deleted; the archive lifecycle preserves the historical records. Similarly, a Contact still referenced by any current monitor role cannot be deleted until that role is removed/reassigned.

## Configuration

Runtime configuration comes from process environment variables and the root `.env` file. Process environment variables take precedence.

Pulse does **not** maintain a parallel database-backed application settings layer.

The administrator UI uses `EnvironmentFile` as the persistent read/write boundary. It preserves unknown keys/comments, encodes values safely, and atomically replaces the file.

Existing SMTP passwords and web-cron tokens are not rendered back into HTML.

The public base URL and database connection settings are installation-level values created by `public/install.php` and kept read-only in normal Administration.

Pulse 1.2 expects the configured base URL to be a site origin without a URL path, matching the site-root routing model.

## Database migrations

Pulse keeps ordered SQL migrations in `database/migrations/`.

The stable line begins with:

```text
001_initial_schema.sql
```

which contains the complete 1.0 baseline. Stable post-1.0 releases add new numbered migrations; the current sequence ends with `006_totp_two_factor_authentication.sql` in Pulse 1.2.3.

At startup, Pulse acquires a database advisory lock, verifies previously applied migration checksums, and applies pending migrations in order.

Once a migration has shipped in a stable release, do not edit it. Future schema changes must be added as new numbered migration files.

`database/schema.sql` is the current reference schema and is not an upgrade mechanism.

## Account security methods

Account authentication beyond the password is represented by generic `user_security_methods` records. Method-specific credential data is stored separately: passkeys use `user_passkey_credentials`, while Pulse 1.2.3 stores encrypted authenticator secrets and replay counters in `user_totp_credentials` and one-time recovery-code hashes in `user_totp_recovery_codes`. `user_security_profiles` provides the stable opaque WebAuthn user handle.

`SecurityChallengeService` is method-neutral challenge storage in the browser session. `PasskeyService` implements WebAuthn registration/assertion verification. `TotpService` coordinates short-lived enrollment/login state, `TotpAlgorithm` implements the RFC-compatible code calculation, `TotpSecretProtector` encrypts secrets and keys recovery-code hashes, and `TotpCredentialRepository` performs atomic counter/code consumption. `SecurityAttemptThrottleService` reuses the opaque login-attempt store with operation-specific keys.

Password verification and authenticated-session establishment are separate operations. Accounts without TOTP proceed directly; enabled accounts receive a short-lived second-factor challenge. Passkey login remains complete authentication and clears any pending password/TOTP state after the assertion is bound to the intended account.

Quick check-in is also separated from authentication. `QuickCheckInService` creates hashed, expiring, cycle-bound email pointers. After the pointer is resolved, either passkey or password authentication must succeed before `MonitorExecutionService::CheckInAllActiveForUser()` is invoked.

## Document storage

Uploaded document payloads are:

- validated for size and MIME type;
- given random internal storage names;
- stored under private `storage/uploads/` outside `public/`;
- streamed only through authorization-checked endpoints.

Editable Markdown text documents and message/portal source text are stored in the database. Pulse stores the source rather than generated HTML. `MarkdownRenderer` produces sanitized web HTML, email HTML with inline CSS, and readable plain-text mail alternatives without a runtime Composer dependency. Raw HTML is never trusted as Markdown output.

This is private authenticated storage, not encryption at rest.

## Localized content

`LanguageCatalog` discovers installed languages from `app/Lang/*.php`; each file declares its native display name through `_language.name`.

`monitor_mail_templates` stores language-specific monitor defaults for:

- recipient notification email;
- safety invitation email;
- safety reminder email.

`monitor_portal_templates` stores the language-specific monitor-wide page introduction. Personal portal messages exist only as recipient-specific overrides attached to the monitor-contact assignment.

Recipient-specific overrides are attached to the monitor-contact assignment. Their text and enabled state are stored independently, so disabling a personal message preserves the draft for later reuse. At release time, Pulse includes the personal message only when that recipient's option is enabled and its text is non-empty. Portal rendering happens from the delivery snapshot. Mail queue rows likewise retain the composed Markdown-capable body source; SMTP delivery derives `text/plain` and `text/html` MIME alternatives from it.

The app name is fixed as **Pulse** in built-in message templates. `{app}` remains accepted when composing an existing custom template for backward compatibility, but it is no longer advertised in editors or used in defaults.

Owners and contacts have four bounded email slots, each with its own checked timestamp. Safety requests and recipient releases copy the checked addresses into dedicated snapshot tables. One logical notification produces an independent queue row per snapshotted address while lifecycle state continues to represent the person/delivery rather than treating each mailbox as a separate recipient.

`Translator` falls back to English for missing keys in an additional language so a partial translation remains usable during development.

Public safety/recipient language selection uses token-scoped preferences where required so changing one public delivery's language does not overwrite an unrelated public flow.
