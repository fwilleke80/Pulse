# Changelog

## 0.7.6 — 2026-08-13

### Drop-in languages and safety-page locale fixes

- Made `app/Lang/*.php` the source of truth for installed Pulse languages; adding a language file automatically adds it to interface selectors and localized monitor mail-template tabs.
- Added native language metadata via `_language.name`, used consistently in footer switches, profile/contact language selectors, and monitor configuration.
- Added English translation fallback for missing keys, allowing a new or incomplete language file to remain usable while translation work is still in progress.
- Removed the remaining hard-coded English/German locale checks from the safety-contact confirmation flow and derive supported locales from the discovered language catalog.
- Fixed safety-contact confirmation pages ignoring manual language changes: an explicit switch is now stored per safety token and overrides the request default without changing another safety request or the broader Pulse UI session language.
- Added the intended locale to newly generated safety-confirmation URLs, keeping the confirmation page aligned with the language selected for the invitation/reminder email.
- Kept existing safety links compatible by falling back to the locale snapshotted into the safety request when no locale is present in the URL.

## 0.7.5 — 2026-08-12

### Localized mail templates and cleaner escalation UI

- Replaced language-independent monitor-wide email overrides with per-language templates keyed by contact **Pulse interface language**.
- Added separate English and German variants for the default recipient email, safety-contact invitation, and safety-contact reminder.
- Kept recipient-specific personal messages as a single per-person override, since they already belong to one known recipient.
- Added migration `012_localized_monitor_mail_templates.sql`; existing 0.7.4 custom monitor-wide text is copied to both English and German during upgrade so no wording is lost.
- Snapshot the selected language-specific safety template into each safety request, preserving in-progress escalation wording even if the monitor is edited later.
- Select the language-specific default recipient template when staging each recipient release.
- Reworked mail-template editing with compact English/Deutsch tabs and collapsible Pulse-default previews.
- Split safety configuration into collapsible **Safety contacts & timing** and **Safety-contact email text** sections, and hide all safety-only settings when **Direct escalation** is selected.
- Show each safety contact's configured Pulse interface language alongside their address so template routing is visible while configuring the monitor.

## 0.7.4 — 2026-08-12

### Debug escalation flow and placeholder guidance

- Made `PULSE_DEBUG=true` lifecycle actions follow the active check cycle's snapshotted escalation policy instead of skipping directly from the owner due notice to recipient delivery.
- Added **Send safety contact notification now** for safety-contact monitors; it starts the real safety gate and immediately processes the initial safety-contact mail through the normal queue.
- Kept **Send recipient notification now** as the following debug step once the safety-contact gate is active, while direct-escalation monitors continue to move directly to recipient notification.
- Expanded every configurable-mail placeholder hint with a plain-language explanation of what each placeholder inserts, including safety URL and reminder-counter placeholders.

## 0.7.3 — 2026-08-12

### Recipient templates and document metadata

- Added `{app}`, `{name}`, `{owner}`, and `{monitor}` placeholder expansion to monitor-default and recipient-specific final-recipient emails.
- Added a localized built-in recipient email fallback, selected from each contact’s Pulse interface language, so monitors no longer require custom recipient prose before escalation can proceed.
- Showed the supported placeholders directly beside recipient message editors and displayed the exact Pulse fallback templates for recipient and safety-contact mail in the UI.
- Kept custom recipient text wrapper-free: when custom subject/body are configured, Pulse sends only the expanded text the owner wrote.
- Added editable display titles and optional descriptions for uploaded file documents without renaming the private stored file.
- Combined uploaded-file title, description, and recipient assignment editing in one document form.
- Added migration `011_document_descriptions.sql`.

## 0.7.2 — 2026-08-12

### Custom safety-contact mail text

- Added editable monitor-level subject and body text for the initial safety-contact email and safety-contact reminders.
- Added placeholders for contact name, owner name, monitor name, confirmation URL, and reminder counters.
- Snapshot custom safety text when a safety-contact request begins so later reminders remain consistent for that request.
- Keep localized Pulse defaults as an explicit fallback when a custom subject/body pair is left empty.
- Renamed the contact language setting to **Pulse interface language** and clarified that it controls Pulse-owned pages such as safety confirmation and the future recipient portal, plus fallback Pulse-authored mail.
- Added migration `010_custom_safety_messages.sql`.

## 0.7.1 — 2026-08-12

### Recipient and escalation interface fixes

- Reworked recipient overview cards into a balanced three-column layout with readable identity, email configuration, document count, and a compact edit action.
- Fixed legacy or empty contact notification locales so the interface shows the resolved language name instead of an internal `notification.language` key.
- Renamed the ambiguous recipient message state to **Email text: Recipient-specific** or **Email text: Monitor default** and linked recipient configuration to **Safety & escalation**.
- Replaced recipient document cards with a compact, scrollable checkbox list that remains manageable with many documents.
- Fixed the direct/safety escalation selectors so each radio button, title, and explanatory text stays inside one fully clickable option card; radio inputs are excluded from the global full-width form-control rule.
- Changed recipient delivery so the configured subject and email body are the exact outgoing email; Pulse no longer adds an unseen localized wrapper around the owner's text.
- Prevented **Awaiting check-in** status pills from wrapping and gave the monitor status column enough room for the full label.
- Replaced the Dashboard **Monitors** total card with **Paused monitors**, alongside **Active monitors**.

## 0.7.0 — 2026-08-12

### Recipient notification

- Added actual recipient notification email after the complete owner and optional safety-contact process.
- Snapshot the recipient name, checked address, language, subject, and body into immutable release deliveries before queueing.
- Localize the final wrapper in the recipient's stored language while preserving the owner's configured message.
- Keep documents gated: no recipient or safety email contains document content, an attachment, or a document-access URL.
- Record **Escalated** only after SMTP accepts the first recipient message; a total delivery failure remains truthfully **Overdue**.
- Added blocked, pending, partial, sent, failed, and cancelled release state plus per-recipient delivery history and retry recovery.

### Optional safety-contact gate

- Added a direct or safety-contact policy to each monitor with independent response, reminder, quorum, and postponement settings.
- Added one-or-more checked safety-contact assignments and an **Awaiting safety contact** lifecycle state.
- Added random 256-bit response tokens resolved through SHA-256 hashes, with expiry and support for valid invitation and reminder links; successful or cancelled safety mail redacts the raw URL from the queue body.
- Made safety GET pages scanner-safe and read-only; only an explicit CSRF-protected POST records confirmation or inability to confirm.
- Allowed the configured confirmation quorum to postpone a cycle while preventing safety contacts from accelerating delivery or accessing recipient content.
- Started and expired the safety clock only from successfully delivered mail state.

### Recipient configuration and operations

- Added dedicated monitor-recipient pages with reusable contact details, default or personal messages, future document assignments, localized preview, and immutable delivery history.
- Added blocked-release and generic notification-failure warnings to the dashboard and monitor overview.
- Added safety and recipient events to recent and complete activity history.
- Added a non-production debug action that can deliberately bypass remaining wait periods and send real recipient messages after an explicit warning.
- Added migration `009_recipient_escalation.sql`, expanded bilingual UI and mail copy, and regression coverage for lifecycle, token, delivery, and rendering boundaries.

### Documentation and version resilience

- Added a monitor-seriousness tutorial with gentle, important, and high-consequence timing profiles.
- Extended the installation, upgrade, user, architecture, and security documentation for recipient delivery and safety contacts.
- Documented the required pre-upload `python3 tools/write_version.py` step that generates `config/version.php`.
- Made a missing generated version non-fatal and display a localized **version unavailable** label with a stable unversioned asset key.

## 0.6.4 — 2026-08-12

### Notification copy and development controls

- Expanded the initial due notice with the configured response-window length and maximum number of follow-up reminders.
- Reworked the German due-notification copy into readable paragraphs with the same explicit sign-in link and action structure as the English message.
- Removed `PULSE_ALLOW_FORCE_DUE`; non-production `PULSE_DEBUG=true` now controls all development-only lifecycle actions.
- Added **Send due notification now** after **Force due now**, using the real transactional queue and SMTP worker without waiting for the next cron tick.
- Kept development actions unavailable in production even if `PULSE_DEBUG=true` is accidentally configured.
- Added localized interface feedback, mail-copy tests, debug-boundary regression coverage, and updated documentation.

## 0.6.3 — 2026-08-12

### Immediate due notifications

- Send an owner notification as soon as a monitor becomes due instead of remaining silent throughout the response window.
- Treat configured reminders as follow-ups: reminder 1 is sent when the response window closes, with later reminders following the configured interval.
- Keep **Maximum follow-up reminders** separate from the initial due notice, including the valid zero-follow-up configuration.
- Record successful due-notice delivery independently in `check_cycles.due_notice_sent_at` and lifecycle activity.
- Require both the due notice and all configured reminders to have been accepted by SMTP before a monitor may become **Overdue**.
- Surface permanent failures of either the due notice or a reminder as a check-in email delivery warning while retaining the truthful **Awaiting check-in** state.
- Cancel pending due notices as well as reminders when a monitor is checked in or paused.
- Preserve upgrade behavior by treating cycles that already delivered a 0.6.2 reminder as already notified.
- Added migration `008_immediate_due_notifications.sql`, bilingual mail copy, queue and lifecycle tests, and updated documentation.

## 0.6.2 — 2026-08-12

### Web cron

- Added `public/cron/cron.php` for hosting services that can schedule only a URL.
- Protected web execution with a required long `PULSE_CRON_TOKEN`, constant-time comparison, no login session, no operational URL parameters, and minimal responses.
- Made the web endpoint run only the same combined scheduler/worker operation as `notifications:run`, using the configured queue batch size.
- Retained command-line notification commands as an equivalent deployment option.

### Recipient notification languages

- Added a notification language to the owner profile for owner reminders and test messages.
- Added an independent notification language to every contact in preparation for later recipient delivery.
- Made queued mail use the recipient's stored language rather than the active interface language.
- Kept existing users and contacts compatible by falling back to `PULSE_DEFAULT_LOCALE` until an explicit language is saved.
- Added migration `007_recipient_notification_languages.sql`, bilingual UI copy, tests, and deployment documentation.

## 0.6.1 — 2026-08-12

### Notification readiness

- Added a prominent Dashboard warning while mail delivery is disabled, explaining that Pulse cannot send reminders or advance active monitors reliably.
- Linked the Dashboard warning directly to **Profile → Notifications**.
- Replaced the apparently actionable disabled test form with a visibly disabled, non-submitting button and an explanation of the required server configuration.
- Changed the disabled-mail status badge from neutral grey to a critical red state.
- Added bilingual interface copy, responsive warning styles, regression coverage, and updated SMTP setup documentation.

## 0.6.0 — 2026-08-11

### Notification infrastructure

- Added authenticated SMTP delivery with implicit TLS and STARTTLS support, UTF-8 plain-text messages, and header-injection protection.
- Added a durable transactional mail queue with immutable message snapshots, idempotency keys, attempt counters, bounded retry delays, permanent-failure state, and delivery-attempt history.
- Added concurrent worker claims using database row locks, `SKIP LOCKED`, unique worker identities, and expiring leases that recover jobs abandoned after a crash.
- Added the `notifications:run`, `notifications:schedule`, `mail:work`, `mail:test`, and `mail:retry-failed` command-line operations.
- Added owner check-in reminders. Recipient messages, documents, and access links remain inactive.
- Count reminders only after SMTP accepts the complete message; only then can the cycle eventually become **Overdue**.
- Keep monitors with permanently failed reminders at **Awaiting check-in**, show a prominent delivery-failure warning, and allow failed jobs to be requeued from the profile page.
- Cancel reminders that have not begun sending when their cycle is confirmed or paused.

### Interface and fixes

- Added **Profile → Notifications** with mail state, pending/sent/failed counts, the latest test result, end-to-end test delivery, and manual retry for failed notifications.
- Added reminder-sent lifecycle activity to the dashboard.
- Limited dashboard activity to the latest 10 entries and added a complete paginated activity history.
- Fixed **Save monitor settings** always returning to **Review & activation**; it now preserves the tab from which the settings were saved.
- Added migration `006_notification_infrastructure.sql`, bilingual notification copy, queue integration tests, SMTP safety tests, and updated deployment documentation.

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
