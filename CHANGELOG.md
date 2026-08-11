# Changelog

## 0.5.0 — 2026-08-11

### Reliable check-in lifecycle

- Added a persisted check-cycle state machine with legal `scheduled`, `awaiting`, `overdue`, `escalated`, `confirmed`, and `cancelled` transitions.
- Added migration `005_check_in_lifecycle.sql`, including upgrade conversion and repair of missing, paused, or duplicate open cycles.
- Made cycle creation, check-in, pause, resume, due-state changes, and audit entries atomic database operations protected by monitor-row locks.
- Snapshotted each cycle's UTC due time, response deadline, reminder interval, reminder limit, and actual reminder count.
- Stopped inferring **Overdue** from elapsed time alone; the notification worker must record that all owner reminders were actually sent before making that transition.
- Prepared explicit notification-only transitions from **Awaiting check-in** to **Overdue** and then **Escalated** without sending mail in this release.

### Global check-in

- Replaced individual due-monitor buttons with one prominent **Check in now** action.
- A check-in confirms every active monitor in one transaction, including monitors not yet due, while leaving paused monitors untouched.
- Each monitor starts its own next interval from the shared confirmation time, so different interval lengths remain independent.
- Added explicit handling and a warning when a late check-in follows an already escalated cycle.

### Dashboard and monitor controls

- Expanded the dashboard with active and attention counts, a complete monitor-status overview, local-time scheduling details, and recent lifecycle activity.
- Replaced the pause settings checkbox with immediate **Pause** and **Resume** actions on the dashboard, monitor overview, and editor review tab.
- Pausing cancels the open cycle and clears the active due date; resuming counts as a fresh confirmation and schedules a new interval from that moment.
- Updated the monitor overview with a single global check-in control and per-monitor pause/resume actions.
- Added bilingual interface copy, lifecycle state-machine tests, integration coverage, and rendered light/dark interface checks.

## 0.4.2 — 2026-08-11

### Interface refinement

- Made monitor titles in the overview link directly to their editors and removed the redundant **Edit** action button.
- Reduced the monitor table's minimum width now that the actions column needs less space.
- Moved **Monitors** before **Contacts** in the main navigation to match the primary workflow.
- Replaced the separate **Back to monitors** link with a **Cancel** button beside **Save monitor settings**.
- Fixed language switching on parameterized pages so the monitor ID and selected editor tab are preserved.

## 0.4.1 — 2026-08-11

### Interface fixes

- Restored a dedicated Description column in the monitor overview and prevented action buttons from wrapping onto a second row.
- Made monitor tabs server-rendered and link-backed, preventing the raw all-panels-visible state while CSS and JavaScript load and preserving navigation when JavaScript is unavailable.
- Added versioned CSS and JavaScript URLs so updated interface assets are not mixed with stale cached files.
- Added a visible **Check address** action beside unchecked recipients in the monitor editor and return navigation after saving the contact.

## 0.4.0 — 2026-08-11

### Monitor configuration

- Rebuilt the monitor editor as four accessible, progressively enhanced tabs: Schedule, Recipients, Messages & documents, and Review & activation.
- Added a persistent monitor-settings save bar and tab-specific validation redirects.
- Added a default monitor subject and message plus optional recipient-specific overrides.
- Added a configuration review with recipient, message, document, address-check, and activation warnings.
- Changed post-creation flow to continue directly into the monitor editor.

### Contacts

- Added explicit owner confirmation that a recipient email address was checked.
- Kept contact entry completely silent: Pulse sends no invitation, consent request, or verification message.
- Added conservative warnings for common email-domain typos.
- Added checked/not-checked address status to contact and monitor views.
- Legacy contacts remain unchecked until their owner reviews them.

### Documents

- Added editable text documents stored in the database.
- Added create, edit, recipient-assignment, and delete actions for text documents.
- Unified recipient selection across text documents and uploaded files.
- Fixed successful uploads with an empty optional title displaying `Document "" uploaded.`; the message now uses the uploaded filename.
- Added a prominent reminder that messages, text documents, and uploaded files remain unencrypted at rest.

### Status and foundation

- Replaced Scheduled/Due terminology with Checked in, Awaiting check-in, Overdue, Escalated, and Paused.
- Added persisted escalated-cycle awareness and a calculated overdue window based on response and reminder settings.
- Added migration `004_complete_configuration.sql`, bilingual interface copy, status tests, email validation tests, and configuration regressions.

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
