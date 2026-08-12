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

Editable text documents and message text are stored in the database.

This private storage model prevents direct public file access, but it is not encryption. The current release does not yet provide encrypted document/message storage or recipient document delivery.
