## 0.9.4 - 2026-08-14

### Fixed
- Added migration `019_normalize_utf8mb4_collation.sql` to normalize every Pulse application table to `utf8mb4_unicode_ci`. Earlier migrations created some later tables with the server/database default collation, which could cause `Illegal mix of collations` errors on fresh installations whose database defaults to `utf8mb4_general_ci`.
- Updated the reference schema so every table declares the same explicit `utf8mb4_unicode_ci` collation.
- Authenticated sessions now resolve to an existing active user on every request. Sessions belonging to deleted or deactivated accounts are invalidated automatically instead of remaining authenticated or entering redirect loops.
- Installer locking, navigation, detected base URLs, and the final login link now preserve an installation subdirectory instead of assuming Pulse is mounted at the domain root.
- Added the missing `actions.save` translation used by the no-JavaScript language selector in all shipped languages.

### Audit
- Began the structured pre-1.0 audit covering installation/bootstrap, authentication/authorization, monitor execution, recipient delivery, mail processing, database integrity, UI consistency, and release packaging.

## 0.9.3 - 2026-08-14

### Changed
- Changed **Add contact** on the Contacts page from a plain text link to the same button treatment used by **Add monitor**, keeping empty-list actions visually consistent.
- The installer now pre-fills **Public base URL** from the address used to open `install.php` instead of allowing the placeholder from `.env.example` to override the detected address.
- Base-URL detection recognizes the common `X-Forwarded-Proto` case so an HTTPS site behind a reverse proxy is suggested as HTTPS rather than HTTP. The detected value remains editable and is still validated before saving.
- Reworded the Public base URL help text to explain that the detected address is a suggestion and normally does not need to be changed.

### Fixed
- Removed a duplicate application-stage completion write in the installation service.

### Documentation
- Updated the installation guide for automatic public-URL detection and the 0.9.3 release.

## 0.9.2 - 2026-08-14

### Added
- Added the guided `public/install.php` first-run wizard with system checks, tested database setup, public URL/timezone/language configuration, automatic migrations, first-administrator creation, optional SMTP configuration, and final verification.
- Added resumable non-secret installation state under `storage/` so an interrupted fresh installation can safely continue without keeping database or administrator passwords in the state marker.
- Added automatic generation of secure first-boot defaults, including trusted-host settings, session/security defaults, upload defaults, and a cryptographically random web-cron token.
- The installer finish page shows the generated web-cron URL and directs the administrator to test SMTP after login.

### Security
- Normal Pulse requests, the public web-cron endpoint, and command-line notification workers refuse operation while `public/install.php` exists.
- A completed existing installation is detected without modification; on upgrades the installer never reinitializes configuration, migrations, or users and only attempts to remove itself.
- Finalization verifies `.env`, database connectivity, schema state, and an active administrator before the installer can remove itself.
- `public/install.php` attempts to delete itself after successful verification. If server permissions prevent deletion, Pulse remains locked until the file is removed manually.
- Installer secret inputs are never redisplayed. Database passwords already written during an interrupted installation can be retained by leaving the field blank.

### Changed
- Fresh installations no longer require manually copying or editing `.env`; the installer creates it from `.env.example` and keeps `.env` as Pulse's single configuration source.
- **Administration → Installation** documentation now reflects that public URL and database values are created by the installer and intentionally remain read-only afterward.
- Declared PDO MySQL and OpenSSL explicitly in Composer requirements to match Pulse's actual production requirements.

### Documentation
- Reworked the installation and upgrade guide around the browser installer, automatic self-removal, first administrator creation, optional SMTP setup, and generated cron token.

## 0.9.1 - 2026-08-14

### Changed
- Simplified **Administration → General**: Pulse now has a fixed application name, timezone selection uses the standard IANA timezone list, and internal `.env` variable names beneath settings have been replaced with short purpose-oriented help text.
- Moved the public base URL to the read-only **Administration → Installation** section. Pulse still requires the value for absolute links, but routine administration can no longer accidentally repoint a live installation to another host; the upcoming installer will own this setting.
- Made Contact names in the Contacts list and Recipient names in Monitor Editor → Recipients open their respective editors directly, matching the clickable Monitor names. The redundant Edit buttons were removed.
- Reworked existing document-card actions so **Save text document** / **Save document details** stays left and **Delete document** stays right on the same row without a separating rule.
- Updated the recipient/document help text to refer to selecting a recipient rather than an Edit recipient button.

### Configuration
- `PULSE_APP_NAME` is no longer read by Pulse or included in `.env.example`; the application identity is always **Pulse**. `PULSE_MAIL_FROM_NAME` remains configurable and defaults to Pulse.
- Existing installations may keep an old `PULSE_APP_NAME` line in `.env`; it is harmless and ignored.

### Documentation
- Updated the Administration, installation, architecture, and security documentation for the fixed application identity, read-only base URL, timezone selector, and revised setting help.

## 0.9.0 - 2026-08-14

### Added
- Added an administrator-only **Administration** area with explicit server-side role authorization and responsive tabs for General, Security, Files, Mail, Cron, and Installation.
- Added migration `018_administrator_role.sql`; all accounts from pre-0.9.0 single-user installations are promoted to `administrator`, while the schema is ready for non-administrator users later.
- Added a configuration-health summary and per-tab warning indicators for actionable setup problems such as disabled mail, missing web-cron token, an unwritable `.env`, or process-level environment overrides.
- Added safe `.env` editing that preserves comments and unknown keys, using atomic replacement when possible and an exclusive-lock fallback when only the file itself is writable.

### Changed
- Moved all SMTP configuration, test-mail controls, retry actions, queue inspection, and debug queue controls from **Profile** to **Administration → Mail**. Profile now contains user-specific account data only.
- Mail queue status in Administration is installation-wide rather than scoped to the currently signed-in owner, preparing operations for future multi-user support.
- Runtime application settings are now maintained through Administration while remaining backed by the single root `.env` configuration source. Database credentials remain read-only because they are required before Pulse can boot; the planned 0.9.x installer will own initial bootstrap configuration.
- Dashboard mail-configuration warnings now direct administrators to **Administration → Mail**.
- Added `PULSE_SESSION_NAME` and `PULSE_HSTS_ENABLED` to `.env.example` so the documented environment surface matches the Administration editor.

### Security
- Administrator routes return HTTP 403 to authenticated users without the `administrator` role; navigation visibility is not relied on for authorization.
- Existing SMTP passwords and web-cron tokens are never rendered back into the Administration form. Blank secret fields preserve the current value; clearing a secret requires an explicit action. New web-cron tokens can be generated locally in the browser so they can be copied before saving.
- Saving unrelated settings does not copy process-level secret overrides into `.env`.

### Documentation
- Updated installation, user, tutorial, security, architecture, and README documentation for the new Administration workflow.

## 0.8.9 - 2026-08-14

### Added
- Added recipient-controlled **Close access permanently** for released portals with no automatic expiry.
- Added a dedicated irreversible-action confirmation page with Download all, an acknowledgement checkbox, and a random easy-to-type confirmation code.
- Added recipient-closure audit/status tracking so owner history distinguishes recipient closure from owner revocation.

### Security
- Permanent recipient closure invalidates the portal invitation, outstanding access codes, queued access-code mail, and the current authenticated portal session without deleting the owner's stored documents.
- Deliveries with an automatic expiry do not expose recipient-controlled permanent closure.

## 0.8.8 - 2026-08-14

### Changed
- Recipient Editor → **Notification email** now shows the inherited **current default template** in the same compact collapsible disclosure used for other default/fallback texts.
- The recipient-specific notification override remains the primary visible editor; expanding the disclosure reveals the inherited default subject and raw body template with placeholders.
- Updated the disclosure label consistently in English, German, French, and Italian.

### Packaging
- Release archives continue to omit `public/secret0410/` and its contents.

## 0.8.7 - 2026-08-14

### Changed
- Renamed the monitor-wide portal fields to **Default personal portal message** and **Page introduction** so their roles are clearer.
- Reworked the built-in portal defaults: the personal portal message now focuses on the material prepared by the owner, while the page introduction explains what the private Pulse page is and why the recipient is seeing it.
- Updated the corresponding recipient-editor labels and built-in default previews in English, German, French, and Italian.
- Reformatted the French and Italian language files to use the same key order, section spacing, and blank-line structure as English and German.

### Packaging
- Release archives continue to omit `public/secret0410/` and its contents.

## 0.8.6 - 2026-08-14

### Changed
- Recipient **Notification email** now distinguishes the raw current default template from the rendered email preview. The default template displays placeholders such as `{url}` literally, while the separate preview shows their recipient-specific example values.
- Safety-contact invitation and reminder placeholder help now appears directly below the corresponding message body field, matching the Recipient email and Portal page editors. Reminder-only placeholders `{number}` and `{total}` are shown only below the reminder body.
- Portal-page defaults are now disclosed directly beneath each corresponding field: **Default portal message** and **Download page introduction** each have their own compact **Show Pulse default text** section.

### Packaging
- Release archives continue to omit `public/secret0410/` and its contents.

## 0.8.5 - 2026-08-14

### Changed
- Reworked Monitor Editor and nested editor tabs into one shared responsive tab component. Tabs wrap into a two-column grid on phones and a single column on very narrow screens; horizontal tab scrolling has been removed.
- Recipient editor sections now use the same fully styled tab treatment as the Monitor Editor instead of appearing as a row of plain text links.
- Dashboard monitor status is now rendered with the same monitor-table styling, status pills, warnings, dates, and compact action buttons as the Monitors page.
- Dashboard monitor actions now reuse the same action partial as the Monitors page, including the complete `PULSE_DEBUG` progression such as **Force Due now**, due notice, safety-contact notification, and recipient notification actions.

### Internal
- Consolidated duplicated monitor action markup into a shared view partial so Dashboard and Monitors page actions cannot drift apart again.

### Packaging
- Release archives continue to omit `public/secret0410/` and its contents.

## 0.8.4 - 2026-08-14

### Added
- Added a persistent **Website language** profile setting separate from the owner **Notification language**. The selected website locale is restored after login and remains available on logged-out/login pages through a non-sensitive locale cookie.
- Added migration `016_website_language.sql`, initializing existing users' website language from their current notification language.
- Added task-focused recipient sub-tabs: **Overview**, **Notification email**, **Portal**, **Documents**, and **History**.
- Added task-focused **Messages & content** sub-tabs for monitor-wide **Recipient email**, **Safety-contact email**, and **Portal page** content.
- Added owner editing of active released portal presentation text and released document display titles/descriptions without changing the immutable recipient/file authorization snapshot.

### Changed
- Reorganized the Monitor Editor into **Details → Schedule → Documents → Recipients → Safety & escalation → Messages & content → Review & activation**.
- Moved document creation/metadata into the monitor **Documents** tab; document assignment is now managed only from each recipient's **Documents** sub-tab.
- Moved safety-contact email wording out of **Safety & escalation** and into **Messages & content**, leaving the escalation tab focused on policy, contacts, timing, quorum, and postponement.
- The recipient summary now uses the same green/red address-status pills as the Contacts list and renames **Edit reusable contact details** to **Edit contact details**.
- Dashboard monitor actions now use the same compact table-button treatment as the Monitors page.
- Main and secondary editor tabs now wrap/reflow for desktop, tablet, phone, and very narrow phone layouts instead of requiring tiny controls or horizontal scrolling.
- The global monitor save bar is shown only on tabs that actually save through the monitor-settings form; task-specific tabs use their own save actions.

### Security / delivery semantics
- Released document membership and file/text payloads remain immutable snapshots. Editing an active delivery can change only recipient-facing presentation text and display metadata, not which files are authorized or their stored contents.
- Monitor defaults and recipient configuration continue to govern future releases; editing active delivery presentation affects only that already released portal.

### Packaging
- Release archives intentionally omit `public/secret0410/` and its contents.

## 0.8.3 - 2026-08-14

### Added
- Added language-specific monitor-wide recipient portal content: a default personal portal message and a configurable download-page introduction.
- Added optional recipient-specific portal-message overrides, separate from recipient notification email text.
- Added migration `015_recipient_portal_messages.sql` and immutable portal-content snapshots on recipient deliveries.
- Mail queue rows in `retrying` state now show a human-readable countdown to the next eligible attempt; terminal failures explicitly state that no further automatic attempts will occur.

### Changed
- Recipient portals no longer reuse the notification email body. The old `[this recipient portal]` substitution has been removed completely.
- Existing delivery portal text is best-effort migrated from the previously stored notification body with the redacted portal-link marker removed.
- Portal text supports `{app}`, `{name}`, `{owner}`, and `{monitor}` placeholders and is resolved using the recipient's configured Pulse language.
- The authenticated recipient page hides expiry information entirely when no automatic expiry is configured.
- The secondary count/size text on the blue **Download all** button is now white for proper contrast.

### Fixed
- Fixed a critical recipient-editor SQL error in 0.8.3: the recipient detail query selected localized portal-message columns without joining `monitor_portal_templates` and `contact_portal_messages`.

### UX
- Monitor-wide portal message/introduction editors are grouped in a collapsible recipient-portal section with language tabs and visible Pulse defaults.
- Recipient-specific portal text is edited on the recipient page and clearly identified as content shown after authentication, independent from the notification email.

## 0.8.2 - 2026-08-14

### Added
- Added a calmer recipient-facing download page headed by the delivery owner, with the released personal message followed by a responsive document-card grid.
- Image documents now appear as lazy-loaded visual previews when their MIME type is safe for inline display.
- Added authorization-checked **View** actions for passive browser-safe formats such as PDF, common raster images, and plain text; active formats remain download-only.
- Document cards now show the recipient-facing title, description, compact file type, file size, and individual **View**/**Download** actions.
- **Download all** now shows the number and total size of available documents before download.

### Changed
- Replaced the temporary-file bulk ZIP implementation with a direct store-only ZIP/ZIP64 stream. Large collections are read from private storage in chunks and written directly to the HTTP response without constructing a second full archive on disk or in PHP memory.
- Recipient download streams release the PHP session lock before sending file payloads so image previews and parallel downloads do not unnecessarily block one another.
- Removed the application-level 100 MiB ceiling on `PULSE_UPLOAD_MAXIMUM_BYTES`; the default remains 25 MiB, while administrators may opt into larger limits subject to PHP/web-server upload limits.
- The authenticated recipient page no longer foregrounds the internal monitor name. Its presentation is deliberately personal rather than administrative.

### Security
- Inline viewing uses an explicit passive MIME allowlist. SVG, HTML, office documents, archives, and unknown binary formats are not rendered inline.
- Image previews and inline views use the same active portal-token and authenticated-session checks as downloads; stored files still have no public storage URL.
- Bulk archives use ZIP64 metadata when classic ZIP size/count/offset limits are exceeded.

## 0.8.1 - 2026-08-14

### Added
- Added the authenticated recipient document portal after successful access-code verification.
- Recipient portals now show the released message plus the immutable document set assigned to that recipient when the delivery was staged.
- Added recipient-facing document title/description display, expandable text documents, secure individual downloads, and a **Download all** ZIP archive.
- Added migration `014_recipient_portal_documents.sql`, including best-effort backfill for existing 0.8.0 sent deliveries.
- Added audit events for individual document downloads and download-all requests.
- Added warning indicators to monitor editor tabs and the monitor list when recipient notification configuration still needs attention.

### Changed
- Recipient-specific and monitor-default recipient mail drafts are now always saveable, even when incomplete or missing the mandatory `{url}` placeholder. Warnings remain visible and actual recipient release stays fail-closed until the configuration is valid.
- Empty/incomplete recipient-specific overrides now remain explicit drafts instead of silently collapsing back to the monitor default.
- Uploaded files referenced by an immutable recipient-delivery snapshot are retained when their editable source document is removed, so an already released portal is not broken by later monitor editing.

### Security
- Portal document listing and download require both a still-valid recipient portal token and the matching authenticated recipient session.
- Document assignments and recipient-facing document metadata/text are snapshotted at release staging time so later monitor edits cannot silently alter an existing delivery.
- Uploaded file payloads remain in Pulse's private non-public storage and are streamed only through an authorization-checked portal endpoint.
- `Download all` uses an internal store-only ZIP writer and does not require the optional PHP zip extension.

## 0.8.0 hotfix - 2026-08-13

### Added
- Added a collapsible mail-queue diagnostics table to Profile → Notifications showing recent queue jobs, status, attempt count, next eligible retry time, recipient, and last error.
- Added a debug-only **Clear queue** control. For safety, it removes only unsent owner/test jobs that Pulse can recreate; safety-contact, recipient-delivery, and access-code jobs are preserved.

### Fixed
- Recipient-release blocking errors now state the concrete configuration problem and name the affected recipients instead of showing only a generic blocked message.
- Recipient cards now flag invalid effective recipient mail configuration, including personal or language-specific default messages that are missing `{url}`.
- Recipient-specific and monitor-default mail editors now show a live warning as soon as a custom body is missing the mandatory `{url}` portal placeholder.
- Default-recipient template save errors now identify the affected language when `{url}` is missing.
- Debug "Send ... now" actions now bypass an existing queue job's retry backoff instead of merely reporting its `retrying` state.
- Permanently failed debug-test jobs can be explicitly reopened with a fresh attempt budget without creating duplicate queue rows.
- Web cron completion logs now include scheduler and mail-worker result counts, making it clear how many queued jobs were claimed, sent, retried, failed, or cancelled.

## 0.8.0 - 2026-08-13

### SMTP diagnostics hotfix
- Preserve the sanitized SMTP server response text for failed operations instead of reporting only the numeric status code.
- Include the mail worker error in warning log context.
- Show the effective non-secret SMTP host, port, encryption, username, password-configured state, and sender address on the Profile page for troubleshooting.


### Added
- Added recipient-specific private portal URLs through the `{url}` recipient-mail placeholder.
- Added the public recipient portal landing page with separate actions to request or enter an access code.
- Added random one-time access codes valid for 30 minutes; only password hashes are stored and requesting a later code invalidates earlier unused codes.
- Added recipient-portal sessions after successful code verification. Document listing/download remains intentionally deferred to 0.8.1.
- Added configurable recipient portal availability: 30 days, 90 days, one year, a custom duration, or no automatic expiry.
- Added per-delivery owner revocation from recipient delivery history.
- Added migration `013_recipient_portal_foundation.sql`.

### Security
- Portal invitation tokens are 256-bit random values stored only as SHA-256 hashes in delivery records.
- Portal lifetime starts only after the recipient notification is successfully sent.
- Recipient notification queue bodies are redacted after delivery/cancellation; access-code bodies are redacted after delivery/cancellation/final failure.
- The public portal never displays the configured recipient email address, and code-request responses are deliberately generic.
- Custom recipient email bodies must contain `{url}`; `{url}` is prohibited in subjects to avoid credential leakage into subject-oriented logs.

## 0.7.8 - 2026-08-13

### Fixed
- Fixed the footer language dropdown so changing the selection submits through Pulse's CSP-safe external JavaScript instead of blocked inline JavaScript.
- Fixed vertical alignment between the footer Language label and the dropdown.

### Added
- Included the French and Italian language files in the source package.

## 0.7.7 - 2026-08-13

### Changed
- Replaced the footer language link list with a compact language dropdown.
- The footer language selector now submits directly when a language is chosen and keeps a small no-JavaScript fallback button.
- Added styling so the footer language selector stays compact even with more installed languages.

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
