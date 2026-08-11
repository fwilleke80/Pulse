# Pulse architecture

## Deployment boundary

The project has one intentionally public directory:

```text
Pulse/
├── app/                 Application code, never public
├── config/              Environment-backed configuration, never public
├── database/            Schema and migrations, never public
├── docs/                Documentation, never public
├── public/              Web-server document root
│   ├── assets/
│   ├── cron/cron.php    Token-protected notification runner
│   └── index.php
├── storage/             Logs and private documents, never public
├── tests/               Development tests, never public
├── tools/               Command-line tools, never public
└── bootstrap.php
```

The web server must use `Pulse/public` as its document root. A URL such as `/monitors` is internally rewritten to `public/index.php`.

The front controller reaches the rest of the application explicitly:

```php
$container = require dirname(__DIR__) . '/bootstrap.php';
```

In that expression, `__DIR__` is `Pulse/public`; `dirname(__DIR__)` is therefore `Pulse`. Root `bootstrap.php` then resolves `app`, `config`, and `storage` relative to its own directory. URL rewriting and PHP filesystem resolution are separate mechanisms.

## Request flow

```text
HTTP request
    ↓
public/.htaccess
    ↓
public/index.php
    ├── trusted-host validation
    ├── security headers
    └── CSRF validation for POST
    ↓
Router
    ↓
Controller
    ↓
Service / Repository
    ↓
View or download response
```

`public/index.php` remains deliberately thin. The root bootstrap builds the services and repositories; the front controller defines routes and performs cross-cutting HTTP checks.

## Layers

### Core

`app/Core` contains framework-like infrastructure:

- `Environment` — process and optional `.env` values
- `Request` — typed, immutable request input
- `Session` — cookie policy and session lifetime
- `CsrfTokenManager` — session-bound CSRF tokens
- `SecurityHeaders` — central response policy and host validation
- `ErrorHandler` — production-safe diagnostics
- `MigrationRunner` — ordered SQL migrations and checksums
- `EmailAddressValidator` — local format validation and conservative typo suggestions without contacting recipients
- `NotificationLanguage` — validates recipient-specific mail languages and legacy fallback
- `WebCronAuthenticator` — authorizes the public background runner with constant-time token comparison
- `Database`, `Router`, `View`, `Translator`, and `Logger`

### Controllers

Controllers enforce authentication and ownership, validate request-level intent, invoke services/repositories, and render or redirect.

Document actions have their own `DocumentController`; they are no longer mixed into the monitor controller.

### Services

- `AuthService` verifies credentials and maintains authentication state.
- `LoginThrottleService` applies account and network limits through opaque hashes.
- `DocumentService` owns upload policy and private filesystem operations.
- `MonitorStateMachine` defines every legal persisted check-cycle transition.
- `MonitorExecutionService` owns cycle creation, UTC scheduling, global check-in, pause/resume, notification-state transitions, and lifecycle audit entries.
- `NotificationScheduler` opens due cycles, creates idempotent owner-reminder jobs, and advances genuinely completed reminder windows to overdue.
- `MailQueueWorker` claims jobs with expiring leases, invokes the transport outside the database transaction, then atomically records success or retry state.
- `NotificationComposer` freezes owner-reminder and test-message content in the recipient's stored language before queueing.
- `TestNotificationService` exercises the same queue and worker path from the authenticated profile action.

`app/Mail` contains the transport boundary and the authenticated SMTP implementation. Later releases will connect recipient delivery and encryption/key services to this lifecycle.

### Repositories

Repositories own prepared SQL. Ownership-sensitive queries bind the current user through the relevant parent record.

`MailQueueRepository` owns idempotent enqueueing, `FOR UPDATE SKIP LOCKED` claims, expiring leases, attempt history, successful reminder accounting, and explicit failed-job requeueing.

`MessageRepository` saves a monitor's default message and all recipient overrides in one database transaction. Its ownership check locks the monitor and accepts override IDs only from that monitor's current assignments.

`MonitorRepository::ReplaceContactsForMonitor()` now synchronizes assignments:

- retained contacts keep the same `monitor_contacts.id`
- new contacts receive a new assignment row
- only removed contacts are deleted
- ordering is updated in place

This is essential because recipient messages and document-recipient links reference the assignment ID.

## Domain model

```text
User
├── Contacts
└── Monitors
    ├── Check cycles
    ├── Default message
    ├── Monitor contacts
    │   └── Recipient-specific messages
    └── Documents
        └── Document-recipient assignments
```

Documents belong to monitors. Delivery selection is modeled independently through `document_monitor_contacts`, allowing one document to be assigned to several recipients without duplicate storage. File contents live in private filesystem storage; editable text documents and their metadata live in the database.

## Monitor timing

Database timestamps are UTC. Each active monitor has one current row in `check_cycles`. `last_confirmed_at` and `next_check_due_at` on the monitor are indexed display/runtime caches; the cycle is the persisted source of lifecycle state.

A cycle snapshots:

- its UTC start and due timestamps
- its response deadline
- the reminder interval and reminder limit in force when scheduled
- the number of owner reminders actually sent
- confirmation, overdue, escalation, or cancellation timestamps

The pure `MonitorStateMachine` allows these transitions:

```mermaid
stateDiagram-v2
	[*] --> scheduled
	scheduled --> awaiting: Due time
	awaiting --> overdue: All reminders sent
	overdue --> escalated: Recipient delivery
	scheduled --> confirmed: Check-in
	awaiting --> confirmed: Check-in
	overdue --> confirmed: Late check-in
	escalated --> confirmed: Late check-in
	scheduled --> cancelled: Pause
	awaiting --> cancelled: Pause
	overdue --> cancelled: Pause
	escalated --> cancelled: Pause
```

`confirmed` and `cancelled` are terminal. A successful check-in closes the current cycle and creates a separate new `scheduled` cycle.

`MonitorExecutionService` locks monitor rows and wraps all affected cycle, monitor, and audit changes in one database transaction. A global check-in selects every active monitor belonging to the user, applies one shared UTC confirmation time, and calculates each new due time from that monitor's individual interval. Paused monitors are excluded.

Scheduled cycles become `awaiting` when their due time arrives. Dashboard and monitor-page requests perform this idempotent synchronization; the cron scheduler performs it for all active users without requiring browser traffic.

Pausing transitions the open cycle to `cancelled`, records `paused_at`, and clears the active due date. Resuming treats the resume instant as a fresh confirmation and creates a new scheduled cycle. Pause and resume therefore cannot revive stale deadlines.

The user-facing status model is:

- **Checked in** — the current cycle is `scheduled`
- **Awaiting check-in** — the current cycle is `awaiting`
- **Overdue** — the notification worker recorded the initial due notice and all configured owner reminders, then transitioned the cycle to `overdue`
- **Escalated** — recipient delivery began and the cycle is `escalated`
- **Paused** — the schedule is deliberately suspended

Elapsed time alone never produces **Overdue**. `MarkCycleOverdue()` additionally requires `due_notice_sent_at`, `reminders_sent >= max_reminders`, and the complete response/reminder window to have elapsed. A permanently failed due notice or reminder leaves the cycle at `awaiting`; the interface adds a delivery-failure warning without falsifying lifecycle state. `MarkCycleEscalated()` is reserved for recipient delivery after that later feature actually begins.

## Notification queue

Each queue row is an immutable delivery snapshot: recipient address, already-localized subject and body, type, cycle, reminder number, and idempotency key. Editing a profile, contact, or notification language after enqueueing does not rewrite an already pending message.

The once-per-minute `notifications:run` command and the protected `/cron/cron.php` web endpoint perform the same two bounded phases:

1. synchronize due cycles and ensure the initial due notice or next eligible reminder exists
2. claim and deliver a limited batch of available jobs

A claim uses an InnoDB row lock with `SKIP LOCKED`, changes the job to `processing`, and assigns a unique worker ID plus an expiry time. SMTP I/O occurs after the claim transaction commits. A second worker therefore skips that job. If the first process dies, a later worker converts the expired lease to `retrying` or `failed` according to its attempt limit.

Successful SMTP completion, the `sent` queue state, `mail_log` entry, corresponding cycle timestamp/counter, and notification audit entry are committed together. The initial due notice records `due_notice_sent_at`; later reminders advance `reminders_sent`. Failed attempts clear the lease and schedule a configured retry. A manual requeue resets only permanently failed jobs. Check-in or pause cancels unsent and failed owner notifications for the closed cycle; a worker also cancels a claimed notification if its cycle is no longer awaiting.

The web endpoint authenticates a normal GET request with `PULSE_CRON_TOKEN`, starts no login session, accepts no run parameters, and takes its batch limit from configuration. The command-line interface remains available for hosts with shell cron.

Notification language belongs to the message recipient rather than the browser session. `users.notification_locale` controls owner reminders and tests; `contacts.notification_locale` is reserved for that contact's later delivery. Null legacy values resolve to `PULSE_DEFAULT_LOCALE`.

The interface converts UTC timestamps to `PULSE_DISPLAY_TIMEZONE`, which defaults to `Europe/Berlin`.

## Configuration

Committed configuration files contain no credentials. `Environment` reads process variables first and then values from an ignored root `.env` file. Process variables always win.

Production mode forces debug output off unless the environment is explicitly non-production. Session, throttle, upload, host, and development controls are centralized in `config/app.php`.

## Migrations

Application bootstrap checks `schema_migrations`, verifies migration hashes, and applies pending `.sql` files in lexical order before handling the request. An up-to-date installation performs only the read-only version check. An installation that needs work acquires a database-specific advisory lock first, so simultaneous requests cannot apply a migration twice.

For a pre-0.3.0 database with application tables but no migration history, it records migrations 001 and 002 as a legacy baseline and then applies all later migrations in order. It never recreates or drops the existing application tables during that baseline operation.

`tools/migrate.php` remains available as an optional command-line diagnostic for development environments. It is not required for installation or upgrades.

Applied migration files must never be edited after release; create a new migration instead.

## Document storage

New uploads are:

- validated using actual file size and Fileinfo MIME detection
- limited by a configurable allowlist
- renamed to a 64-hex-character random basename with a `.bin` suffix
- stored under `storage/uploads/monitor-documents`
- assigned filesystem mode `0600`
- delivered only through an authenticated, ownership-checked controller

This prevents direct web access and executable filename behavior. It is not encryption. Messages and editable text documents are also unencrypted database values in 0.6.3. A later secure-storage release will add authenticated encryption and key versioning without changing the recipient-assignment model.
