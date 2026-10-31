## 1.5.9 - 2026-10-31

- Fix the development-only "Expire safety contact window now" action: skip the active test cycle's remaining safety reminders, cancel undelivered safety-reminder mail, and move its deadline into the past. The regular scheduler then expires the gate (after safety invitations are actually delivered) and follows existing recipient-release safeguards. No production escalation rules or monitor configuration are changed.
- Add a superuser-only, CSRF-protected Clear log action under Administration → Maintenance → Logs, requiring explicit confirmation. It truncates the existing log under an exclusive lock, preserving the file and its write permissions. Log downloads remain available for preserving evidence before clearing.
- Extend the existing test suite with regression coverage for the debug expiry and log clearing. No database migration.

## 1.5.8 - 2026-10-31

- Remove redundant “Next due” from Monitors: “Next action” already shows the check-in deadline while scheduled and the relevant reminder/escalation deadline afterward. Show the original missed check-in deadline within the monitor Overview for context when a cycle is awaiting or escalated.
- When safety-contact verification is active, show an explanation and the current cycle’s immutable response/reminder settings beside the editable configuration. Changing monitor settings does not silently reschedule existing invitations, reminders, or expiration.
- Add focused regressions for the Monitors table and saved safety timing. No database migration.

## 1.5.7 - 2026-10-31

- Fix a safety-critical cron regression: `EscalationService::LockCycle()` now retrieves both postponement fields required to start safety-contact verification. No change to the live escalation configuration is required.
- Add a read-only, superuser-only application log viewer under Administration → Maintenance → Logs, with reverse-order paging, severity filtering, literal search, in-place refresh, and raw log download. Log lines are HTML-escaped; browsing is bounded and does not load the full log into memory.
- Make the four Administration sections fit in one desktop row with responsive wrapping at smaller sizes.
- Bring Monitor list reporting up to the Dashboard standard: include Next action with its due time and description and add interactive sorting to Name, Status, Last check-in, Next due, and Next action.
- Warn when cron is stale or has never completed successfully on the Dashboard, the Monitors list, and monitor Overview; no unqualified green operational-health banner while cron is unhealthy.
- Log cron exception class, source filename, and line alongside the existing message. Public cron endpoint responses remain opaque.
- Extend the existing PHPUnit suite with a real MySQL safety-gate regression and log inspection/health tests; do not introduce a new test pipeline.
- Render actionable URLs in all four languages’ built-in owner, safety-contact, and recipient email templates as Markdown links (`[{url}]({url})`), preserving custom templates and a clean plain-text mail alternative. Add template/renderer regression coverage.
- No database migration.

## 1.5.6 - 2026-10-28

- Sort both document tables by Name, Type or Size using keyboard-accessible header buttons. Click again to reverse the direction; size sorts by bytes, names use natural locale-aware sorting. Keep the chosen ordering through upload/assignment refreshes.
- Display Text, PDF, Image, Audio, Video, Document, CSV, JSON or Other file in both tables and the assignment picker. Descriptive labels do not expand safe-preview eligibility.
- No database migration.

## 1.5.5 - 2026-10-28

- Unify monitor and recipient document lists through one shared, alphabetically sorted Name / Type / Size / Actions table. Keep Delete monitor-only and Unassign recipient-only; refresh the monitor table after drops without replacing unsaved editor fields.
- Clarify that recipient assignments, unassignments and uploads save immediately; no additional recipient configuration save is needed.
- Show document sizes in readable B, KB, MB, GB or TB units (decimal, at most one decimal place).
- Fix Preview for Pulse text documents: load the owned database document and render it through the existing safe Markdown preview instead of requiring an uploaded file.
- Preserve ownership checks, private response headers and uploaded-file preview validation. No database migration.

## 1.5.4 - 2026-10-28

### Saveable drafts and precise validation
- Save incomplete owner, recipient and safety-contact mail templates consistently as drafts with a warning. Both fields empty remains the valid built-in fallback. Invalid saved templates are not staged for sending.
- Add inline subject/body explanations and live main-tab, subsection and language markers. Correcting a draft clears its live markers without claiming the saved monitor configuration is fixed.
- Put saved template problems near the top of Overview, grouped by source template and language, with affected recipients and exact field links. Links open the enclosing tabs/disclosures and focus the field. Remove the misleading no-custom-message warning and duplicate aggregate template counts.
- Explain and link recipient warnings to either the personal template or the appropriate localized default. Personal and inherited template issues are also shown in the recipient editor.
- Use the same language resolver for diagnostics and sending when a contact has no explicit notification language. Personal overrides remain independent.

### Editor audit and delivery safeguards
- Owner draft checks stop invalid messages from being queued; safety gates reject invalid invitation/reminder configurations before progressing. Existing recipient release validation remains fail-closed. Valid already-staged snapshots are unchanged.
- Reveal and mark native invalid fields across tabbed editors and document Markdown Edit/Preview panels, preserving the current form. Hard constraints (required names, numeric bounds, file eligibility and authorization) still prevent invalid writes.
- Allow an empty optional personal portal message consistently with portal rendering. Document draft recovery and per-file upload rejection behavior remain intact.
- See `docs/VALIDATION_AUDIT_1.5.4.md` for the audit scope and verification limits. No database migration or Markdown shorthand was added.

## 1.5.3 - 2026-10-28

### Portal document sections
- Group documents into Text documents, PDFs, Images, Videos, Audio and Other files; omit empty sections. Sort by displayed title, case-insensitively with natural numeric order and a stable ID tie-breaker.
- Share grouping between live recipient portals and owner preview; retain card expansion animation, previews, downloads and immutable released content.

### Recipient document management
- Show only assigned documents in a compact Name / Type / Size / Actions table, without description excerpts.
- Add a searchable Assign documents dialog with multi-selection of currently unassigned monitor documents. Assignment and unassignment save immediately; no separate Save assignments step is needed.
- Row menus provide Edit, Preview and Unassign. Unassign removes the relationship, not the document. Uploaded and newly created documents retain automatic recipient assignment.
- Apply additive/removal deltas transactionally, with ownership, monitor membership and archived-monitor checks. Changes do not rewrite recipient messages or already-released snapshots.
- Refresh only assignment lists after changes/uploads, retaining unrelated unsaved editor fields. Reinitialize dynamically inserted action menus.

### Monitor Overview
- Merge Status and Review & activation into a first/default Overview tab: operational summary, next action, last event, state controls, linked configuration summary, readiness warnings, escalation timeline and history.
- Overview Check-in affects only the current owned active monitor. Dashboard Check-in still covers all active owned monitors. Location consent behavior is retained.
- Keep old Status/Review tab URLs working through aliases. Newly created monitors open Overview after saving. Existing pause/resume, emergency stop and archived-state protections remain in force.

### Validation
- 68 focused PHP tests / 132 assertions passed, including transactional assignment deltas, cross-user/monitor rejection, archived protection, rollback, portal grouping, scoped check-in, authentication/privacy and emergency resume.
- Actual recipient-table and Overview templates rendered with PHP warnings treated as errors. DOM checks cover assigned-only display, picker search, immediate save, unrelated draft preservation, upload behavior, and portal animations/reduced motion.
- PHP and JavaScript syntax checks passed. No new database migration. Validation used PHP 8.3; supported deployment remains PHP 8.4+. Full browser and live-server integration validation remains outstanding. The previously documented full-suite source-assertion failures are not claimed resolved by this release.

## 1.5.2 - 2026-10-27

### Navigation
- Remove the duplicate User accounts entry from the main menu. Administrators continue to reach account management through Administration → User accounts, with all existing permissions and superuser protections unchanged.
- No database migration or authentication changes.

## 1.5.1 - 2026-10-27

### Administration
- Keep the shared Administration navigation visible on the User accounts list and account editor. The top-level menu shortcut opens the same section.
- Move Registration settings to Configuration → Security and return there after saving.
- Replace account cards with a table and clickable account names. Add the standard ellipsis actions menu for editing, explicit invitations, approval when pending, and links to authenticator reset/deletion confirmation. Preserve protected-superuser and self-deletion restrictions; the superuser's own name opens their profile.

### Document drops
- Fix the recipient drop hint/status elements being placed in Notification instead of Documents. This prevented upload handlers from attaching and let dropped files replace the page.
- Show the same visible drop area as the monitor editor, and prevent file drops outside the upload area from navigating away. Preserve existing upload checks and unsaved editor fields.
- The reported login redirect after navigating back from a local file was not reproduced independently. No upload code logs out the user. Preventing the unintended navigation removes that trigger; session-cookie protections are unchanged.

### Dashboard and portal
- Match the Emergency Stop button to Check-in, with red colors and a stop-sign icon. Place their sections in equal-width columns, stacking on narrow screens. Keep Emergency Stop confirmation.
- Animate portal document cards and surrounding cards between expanded/collapsed layouts, including owner preview. Honor reduced-motion preferences and retain lazy preview loading.

### Validation
- 65 focused PHP tests / 117 assertions pass; template rendering and PHP/JS syntax checks pass.
- DOM tests cover the real drop-area template structure, multiple uploads, rejected files, escaped filenames, unsaved state, outside-drop navigation prevention, expansion/collapse, lazy loading and reduced motion.
- No schema migration is added. Validation used PHP 8.3; supported deployment remains PHP 8.4+. Full browser/SMTP validation and reproduction of the reported Safari return-to-login behavior remain outstanding. Existing full-suite baseline failures are documented in the 1.5.0 release notes.

## 1.5.0 - 2026-10-27

### Accounts and privacy
- Create a protected superuser during installation; migrate the original owner to that role. Administrators can manage accounts, roles, login access, passwords and required/reset two-factor authentication. Passwords are never displayed. Administrative roles do not grant access to another user's monitors, recipients or documents.
- Add optional public registration, mandatory email confirmation and an independent optional administrator-approval requirement. Public registration always creates normal users.
- Allow manually provisioned accounts without email invitations. Invitations are sent only through an explicit administrator action. Initial and administrator-reset passwords require a password change.
- Add expiring single-use hashed account tokens, CSRF validation, escaped account forms, throttled public authentication actions, session revocation after account security changes, and account audit events.
- Restrict installation-wide backups, restores and updates to the superuser. These operator functions contain installation data and are not a cryptographic privacy boundary.
- Keep monitoring running when an administrator blocks an account's login.

### Emergency-stop recovery
- Fix ordinary Resume clearing the pause while retaining the emergency-stop flag. Both resume paths now update the monitor transactionally. Explicit Resume also recovers monitors already stuck in that inconsistent state and starts a fresh interval.
- Migration 011 keeps legacy inconsistent monitors safely paused until their owner chooses Resume. Global emergency reactivation preserves pauses that existed before the emergency. Cancelled messages and revoked portals remain cancelled/revoked.

### Document editor improvements
- Support multiple-file drag and drop in the monitor and recipient document tabs, using each file's name as its document name and the existing upload eligibility checks. Report individual failures and preserve unsaved editor fields and document selections.
- Remove the monitor editor's bottom note about settings and message/content changes being saved together.

### Upgrade
- Adds migration `011_multiuser_accounts.sql`; existing sessions must sign in again. Public registration remains disabled by default. See `docs/RELEASE_1.5.0.md` for deployment and validation details.

## 1.4.7 - 2026-10-26

### Monitor escalation spacing
- Restore the standard 1.25rem vertical gap between the Final release grace period card and the escalation-policy choices in Monitor Editor → Safety & escalation.
- CSS-only layout correction. No database migration or behavioral changes.

## 1.4.6 - 2026-10-26

### Recipient portal document cards
- Order each document card as title, file type and size, preview, then action buttons. Keep the optional description with the card header.
- Rename the expansion control to localized Open / Close. When expanded, the existing preview region becomes the full viewer for text/Markdown, PDF, CSV and JSON, or the enlarged media area for images and videos; there is no second preview below the buttons.
- Apply the same responsive layout to the authenticated recipient portal and owner portal preview, keeping lazy loading of embedded files and immutable released-document snapshots.

### Monitor and editor polish
- Move Final release grace period above the escalation-policy choices, using the same configuration-block spacing as other settings.
- Show descriptive, localized browser tab titles containing the actual monitor, recipient or document name, including named documents opened from the recipient editor. New document pages retain a descriptive Create text document title.
- No schema migration is required.

## 1.4.5 - 2026-10-26

### Final release warning and optional grace period
- Add a per-monitor final release grace period of 0–720 hours, defaulting to zero. Recipient releases wait for at least one verified owner-warning email to be accepted by the mail transport before staging recipient notifications. The configured timer starts after that first successful transport acceptance. If no checked owner email can be notified or all attempts fail, release remains blocked rather than proceeding without warning.
- Introduce an owner-warning queue type with highest dispatch priority, per-cycle idempotency, one-use hashed emergency bearer tokens, expiring links and all four localized warning texts. The warning includes a link to a confirmation page; merely visiting the link cannot stop Pulse.

### Emergency stops
- Add an account-wide Emergency Stop button on the Dashboard. It suspends monitoring, cancels outstanding monitor-related mail and check cycles, revokes existing recipient portals and invalidates unused portal access codes. Already-sent emails or downloaded files cannot be retracted.
- The email emergency link instead stops only the monitor named in the warning. The owner can later explicitly reactivate suspended monitors; existing revoked portal deliveries remain revoked. Authenticated Dashboard stops require CSRF validation; email-based stops require a one-use expiring token plus explicit POST confirmation and CSRF validation.
- Add audit events, status indicators, protection against resetting emergency suspension through ordinary resume, and migration `010_release_emergency_safeguards.sql`.

### Responsive recipient portal document cards
- Present document cards in a two-column responsive grid on sufficiently wide screens, falling back to one column on narrow screens. Expanded full-document viewers span the whole grid. The recipient portal and owner preview share the same view.

### Deployment and validation
- Includes the numbered schema migration, source and language regression coverage. Live MySQL/scheduler/mail and browser regression testing is still required before production reliance on the new release controls.

## 1.4.5 - 2026-10-26

### Emergency Stop
- Added an always-available Dashboard Emergency Stop action and explicit authenticated reactivation. Emergency Stop suspends every owned monitor, cancels queued/processing notifications, revokes existing recipient portals, invalidates unused portal codes and audit-logs the action for each monitor. Monitors previously paused remain paused after reactivation. Already-delivered SMTP messages and downloaded files cannot be recalled.
- Added a high-entropy, expiring, one-use emergency link in the owner release-warning email. Link GET is read-only; a separate CSRF-protected POST confirms the stop, avoiding unintended activation by email-link scanners. No ordinary sign-in is required to exercise the token's stop-only authority.

### Owner-first recipient release and optional final grace period
- Added `release_grace_hours` (0–720) per monitor and `release_warnings` release barrier in forward migration `010_release_emergency_safeguards.sql`.
- Prioritize owner release-warning jobs in the mail queue. Block new recipient releases until all owner warnings have finished processing, at least one was accepted by the configured transport, and the grace period (counted from acceptance) has elapsed. Block release if no checked owner email exists or all warning deliveries fail.
- Prevent the debug release action from bypassing this barrier, and show the warning and grace stage in monitor status and audit history. New owner-warning mail content and emergency controls are localized in all four supported languages.

### Responsive recipient portal documents
- Present two equal-width document cards per row when space permits and one column in narrow containers, both on actual recipient portals and owner previews. Expanded readers continue spanning the whole section.

### Verification note
- Syntax, translation and static checks are included; live MySQL/SMTP integration and concurrency tests remain required before production reliance.

## 1.4.4 - 2026-10-26

### Document management from Recipient Editor
- Add Create text document, Upload document and Edit actions to Recipient Editor → Documents, using the existing monitor-owned document editors and upload service. A newly created or uploaded document is automatically assigned only to the recipient being edited; existing assignments and previously released immutable copies are untouched.
- Preserve the recipient context in document editor links, validated hidden form fields and post-save/error redirects. A mismatched monitor-contact ID is rejected server-side to prevent cross-monitor assignments.
- Guard pending recipient document-checkbox changes when entering document editors, uploading a file or navigating away. Keep uploaded file forms separate from the recipient-assignment form and disable editing when the monitor is archived.

### Crash-resistant local drafts for text documents
- Auto-save unsaved title and Markdown text changes in browser-local storage under an owner/monitor/document-specific draft key. The editor shows whether changes are server-saved, saved only locally, or could not be cached.
- Offer Restore draft and Discard draft on reopening a document with a different saved local draft. Do not silently overwrite newer server data. Local drafts are unencrypted and should not be used on shared devices.
- Save text documents asynchronously with an explicit authenticated success response; clear the local draft only after the server confirms success. Preserve the draft on network errors and server rejections. Maintain the existing normal POST behavior as a fallback.

### Less crowded Safety contacts
- Remove the redundant per-contact email-address and language list from Monitor Editor → Safety & escalation. Keep the selected contact's name and checked-address availability status; notification eligibility and sending behavior are unchanged.
- Update all four language catalogs and UI tests. No database migration is required.

## 1.4.3 - 2026-10-19

### Administration overview and navigation
- Replace duplicate Administration Overview navigation cards with a read-only system dashboard showing Pulse version, database/schema migration status, last successful cron run and maintenance state. Move the former System Information page into expandable technical details on Overview. Do not retain the obsolete `/administration/system` route.
- Display all five short Configuration tabs on one row where space allows, with responsive layout on narrow screens. Use matching two-tab navigation for Maintenance (Backup & Restore and Updates).

### Simplified update installation
- After a successful update preflight, hide the ZIP selector and validation action. Show the already-staged, verified release, its checks and release notes, administrator password field and Install action; provide a separate CSRF-protected Change package action.
- Remove the redundant JavaScript Install now question. Keep administrator password reauthentication, staged-file checksum checks and server-side preflight at installation.
- Require a single ZIP-root `manifest.json` with complete file inventory, and reject packages containing the deprecated `docs/update-manifest.json`. Remove legacy output support from the deterministic source ZIP manifest generator. Pulse 1.4.2 can install this root-only release without FTP.
- No database migration is required.

## 1.4.2 - 2026-10-19

### Administration pages
- Reorganize Administration into an Overview, independent Configuration (General, Security, Files, Mail, Cron), Maintenance (Backup & Restore, Updates), and read-only System Information pages. Save settings now appears only on Configuration; maintenance operations keep their own forms.
- Add dedicated URLs for each area and update internal links and four-language navigation. Do not add redirects for obsolete `/administration?tab=` bookmarks. Reserve user management for the future multi-user release.
- Allow Backup & Restore and System Information access during post-restore review. No database migration required.

### Root-level update manifest
- Add canonical ZIP-root `manifest.json` with complete SHA-256 inventory. Include a transitional `docs/update-manifest.json` that also hashes the root manifest so Pulse 1.4.1 can verify and install this update.
- The 1.4.2 inspector prefers the root manifest, verifies the compatibility manifest when supplied, and also supports 1.4.1-era legacy-only packages.
- Update the deterministic Python package builder (`tools/build_update_package.py`) to produce root-only manifests by default, or both manifests using `--include-legacy-manifest` for this transition release. No database migration required.

## 1.4.1 - 2026-10-19

### Installer preflight and release notes
- Add manifest-inventory checks with full SHA-256 file digests, migration hashes, version compatibility, writable destination preflight, PHP requirements, and initial staging-space checks. Preflight displays changelog sections newer than the currently installed release.
- Distribute `tools/build_update_package.py` and `docs/UPDATE_PACKAGES.md` in the complete source; the manifest is in `docs/update-manifest.json` to remain installable by the 1.4.0 updater.
- Stage archives privately for a separate review step and revalidate when installation starts. Retain admin password reauthentication; remove typed INSTALL confirmation, trust checkbox and prominent unsigned-package warning.

### Restore preflight and Administration tabs
- Validate a backup's checksum manifest, SQL import format, TOTP secret, installed version, storage permissions and initial disk space before offering the restore action. Keep password reauthentication, replace typed RESTORE with an explicit replacement-acknowledgement checkbox and final Yes/No confirmation.
- Make Administration tabs wrap to additional rows like Monitor Editor tabs. No database migration is required.

## 1.4.0 - 2026-10-19

### Backup & Restore (new Administration tab)
- Export complete, checksum-manifested database SQL plus uploaded private files and installation environment secrets into one ZIP. Optionally encrypt the actual recovery payload using password-derived AES-256-GCM authenticated chunks; unencrypted backup remains an explicit option with privacy warning.
- Restore a verified same-version backup after administrator password reauthentication and explicit destructive-operation confirmation. Stage files, create an emergency rollback archive, restore database/settings/uploads, rotate the global authentication epoch, and leave monitoring and mail paused until a restored administrator deliberately resumes.
- Reject unexpected/traversal archive members, stage private uploads outside the document root, and coordinate restore/update with web and CLI notification jobs using a maintenance gate. Interrupted or failed operations retain the maintenance lock when manual recovery is required.

### Markdown exports and recipient document excerpts
- Export the current user's editable database-stored text documents as UTF-8 Markdown files grouped by monitor, with metadata and SHA-256 hashes in `manifest.json`; do not include other users' documents or historical delivery snapshots.
- Replace complete small-font recipient text-document previews with ~200-character plain-text excerpts; the complete Markdown still appears in the expanded document reader. Owner portal preview uses the same card.

### In-app updates (new Administration tab)
- Accept manually uploaded newer Pulse release ZIPs, enforce a conservative file allowlist, preserve `.env` and private storage, stage replacements and save originals, then run checksummed numbered migrations. An emergency full backup is retained under `storage/rollbacks/`.
- Require administrator password confirmation and explicit acknowledgement that uploaded update PHP code is **not publisher-signature verified**. A failed migration may require manual database recovery; the maintenance lock stays active rather than resuming the monitor automatically.
- Added four-language interface strings, installation/security notes and archive tests.
- No new database migration is required by 1.4.0 itself.

## 1.3.9 - 2026-10-19

### Safety-contact postponement allowance
- Added optional per-monitor **Maximum consecutive safety postponements** (0 = unlimited/backward-compatible). Successful safety-contact quorums consume one postponement; a personal owner check-in or explicit reset/reactivation restores the allowance. Pausing and resuming retains the counter.
- Once the finite allowance is exhausted, the next overdue cycle skips the safety stage and continues toward recipient release. Existing safety-contact rounds remain valid if a limit is changed in progress. Status and **What happens next** reflect the active allowance.

### Recipient configuration without activation
- Unassigning a recipient now deactivates rather than deletes their monitor-specific record; custom message, portal settings and document selections survive later reactivation. **Configure** remains available for inactive records, and previously unassigned contacts can be configured into inactive drafts.
- Consolidated recipient checkboxes into the main **Save changes** action. Future recipient releases select only active assignments, while immutable released portal history remains unchanged.

### Document editing and activity history
- Added explicit confirmation for leaving a document editor with unsaved changes, plus browser navigation protection. The existing shared Markdown **Edit / Preview** component continues previewing the unsaved draft server-side.
- Persisted each delivered owner follow-up reminder's ordinal and configured maximum in its audit entry; dashboard, full activity history and monitor status show **N of M** where both numbers are recorded.
- Added migrations `008_consecutive_safety_postponements.sql` and `009_inactive_recipient_configurations.sql`, all four UI languages, and source/integration regressions. Deploy against a backup and test on a non-production installation before relying on its lifecycle.

## 1.3.8 - 2026-10-06

### Complete status timeline and document actions
- Extended **Status → What happens next** beyond **Safety-contact verification starts**. Safety-contact monitors now show both conditional branches: enough confirmations postpone the monitor and schedule a new check-in cycle, while an unconfirmed safety gate continues through safety reminders, expiry, and recipient release.
- Kept timeline precision honest: before safety invitations have actually been delivered, later safety reminders and expiry are shown relative to invitation delivery instead of receiving invented absolute timestamps. Once the safety gate has started, persisted timestamps are shown normally.
- Reorganized **Monitor → Documents** so **Create text document** and **Upload document** sit next to each other as the two primary ways to add documents. Upload now expands its form immediately below the action row instead of appearing as a separate folded section farther down the tab.
- Updated all four interface languages, documentation, and regression coverage. No database migration is required.

## 1.3.7 - 2026-10-06

### Active reminder-setting synchronization
- Fixed an awaiting monitor continuing to use the reminder limit and future escalation settings captured before the monitor was edited. Changing **Maximum follow-up reminders** now immediately updates the current awaiting cycle, so **Status → What happens next** and the scheduler use the same current value.
- Awaiting cycles now adopt edited reminder interval, reminder maximum, escalation policy, and future safety-stage timing/confirmation settings while preserving the already-established due time and response deadline for that current cycle.
- Added a delivery guard so an already queued owner reminder whose number is now above the reduced maximum is cancelled rather than sent.
- Added regression coverage and documentation. No database migration is required.

## 1.3.6 - 2026-10-06

### Uploaded document previews
- Added an owner-authenticated inline preview to the dedicated uploaded-document editor for the same passive formats Pulse already renders in recipient portals: PDF, raster images, audio, video, Markdown, plain text, CSV, and JSON.
- Reused the existing `DocumentPreviewService` classification, bounded text rendering, and private range-capable streaming path so the editor and portal cannot drift onto different preview rules. Unsupported or potentially active formats remain metadata + download only.
- Preview requests re-check the logged-in owner, monitor/document ownership, current file availability, and viewability before returning any content. No storage path is exposed and responses remain private/non-cacheable.
- Updated all four interface languages, documentation, and source regression coverage. No database migration is required.

## 1.3.5 - 2026-10-06

### Monitor editor status cleanup
- Removed the four installation-wide Monitor/Mail/Cron/Delivery health indicator cards from the monitor **Status** tab and stopped fetching installation-wide cron health for that page. The existing status banner now reflects monitor-specific lifecycle/delivery issues only; installation-wide health remains in Administration.
- Reduced the **Last event** event-title size to match the **Next action** title size for a more balanced summary row.
- Removed the positive **The core delivery configuration is complete.** note from **Review & activation**. Configuration problems still produce warnings; a valid configuration remains deliberately quiet.
- Updated all four interface languages, documentation, and source regression coverage. No database migration is required.

## 1.3.4 - 2026-10-06

### Monitor editor cleanup and document workflow
- Renamed the sparse **Details** tab to **Basic** while retaining the existing internal tab key and links for compatibility. The top-level monitor tabs no longer wrap individual labels; on narrower desktop widths complete tabs flow into stable additional rows instead.
- Replaced the space-heavy inline document editors with a compact document library showing type, title, modification time, and text/file size metadata. Text documents are created and edited in a dedicated editor, and uploaded-file metadata uses the same separate editing workflow. File upload remains available from a collapsible block on the Documents tab.
- Removed the **Not encrypted yet** note from **Messages & content** and tightened that tab's spacing/grouping. No document storage or encryption behavior changed.

### Status and operational confidence
- Expanded the monitor **Status** tab with compact health indicators for monitor state, mail availability, cron health, and delivery health. Installation-wide mail or cron problems now prevent the page from presenting the monitor as fully healthy.
- Replaced **Last confirmed** with **Last event**, showing the newest meaningful monitor lifecycle event, and promoted **Next action** to a summary card beside the current status.
- Added lightweight event-type badges to the monitor history so check-ins, notifications, safety actions, escalations, failures, and state changes can be scanned quickly without turning the page into a large visual timeline.
- Updated all four interface languages, user/tutorial documentation, and source regression coverage. No database migration is required.

## 1.3.3 - 2026-10-05

### Operational monitor overview
- Replaced the Dashboard monitor table's stale **Next due** display with **Next action**, derived from the persisted current cycle. Awaiting monitors now show the next reminder or escalation step and its eligibility time instead of continuing to display the original due date.
- Added a read-only **Status** tab to each monitor with a normal-vs-attention health summary, the current lifecycle state, a deterministic **What happens next** timeline, cron timing guidance, and recent monitor-specific lifecycle/mail history.
- Kept the overview tied to the same persisted timing snapshots and counters used by the scheduler, including owner reminders, safety-contact reminders/windows, recipient release, queued delivery, and blocked/failed delivery states. No database migration is required.

### Recipient and contact UI
- Replaced the split assigned-recipient list plus small **Add recipient** selector with one scrollable card list containing all contacts. Checkboxes control assignment, existing assignments expose **Configure**, and the list can be sorted by **Assigned first** or **Name A–Z** without changing persisted recipient order.
- Recipient assignment changes are saved explicitly as a batch. Removing an existing assignment requires confirmation because its monitor-specific message, portal, and document configuration is deleted.
- Added a little more vertical separation between the **Name** field and email-address fields in the contact editor.
- Updated all four interface languages, the user/release documentation, and source regression coverage.

## 1.3.2 - 2026-08-20

### Recipient portal editor placement
- Moved **Preview recipient portal** from the recipient editor’s Overview tab to the top of the Portal tab, directly before **Personal portal message**, so the preview action sits with the content it represents.
- Updated the user guide and source regression coverage. No database migration is required.

## 1.3.1 - 2026-08-18

### Cron diagnostics
- Added **Web-cron token last changed** under Administration → Cron. The timestamp advances only when the persisted token value actually changes through Administration; existing installations initially show that no change has been recorded yet.
- Added a bounded history of unsuccessful web-cron calls. Administration displays the newest 20 entries with time, failure reason, HTTP method, and the supplied invalid token when token authentication failed, so stale or mismatched external cron configuration can be diagnosed directly.
- Retain at most 50 failed calls. Invalid supplied tokens are stored exactly at normal lengths; pathological values longer than 512 characters are truncated and marked as such, and all displayed values remain HTML-escaped. Non-token failures do not copy the valid configured token into diagnostics.
- Added migration `007_cron_diagnostics.sql`, updated the reference schema, all four interface languages, security/installation/user documentation, and regression coverage.

## 1.3.0 - 2026-08-17

### Profile and administration clarity
- Labelled the first owner address **Main Email**, made that slot explicitly mandatory in both browser and server validation, and added a clear inline error state when it is empty. The three additional addresses remain optional, and all four stored addresses remain valid login aliases; each address's checked state still controls mail delivery independently.
- Placed the browser-generated cron-token explanation on its own line below **Generate strong new token**.

### Recipient and safety-contact guidance
- Moved **Adding a recipient is silent** into the **Add recipient** block so the explanation sits beside the action it describes.
- Added concise field help to every value in **Safety timing & confirmations**.
- Replaced the previous blocking safety-contact validation with a live advisory warning when **Confirmations required** exceeds the selected contacts that currently have at least one checked email address. Incomplete setups may be saved for later completion, and the same warning is reflected on the Review tab.
- Updated all four interface languages, documentation, and source regression coverage. No database migration is required.

## 1.2.7 - 2026-08-17

### Quieter help and clearer primary actions
- Restyled persistent form help throughout Pulse as smaller, muted copy placed closer to its field, retaining touch, keyboard, and screen-reader availability without hiding essential guidance behind hover-only bubbles.
- Gave the global **Check in now** action a distinctive friendly confirmation treatment on Dashboard and Monitors, removed redundant schedule explanations, and reduced Dashboard recent activity to five items while retaining the complete-history link.
- Replaced the Monitors active/archive switch with responsive **Active monitors** and **Archived monitors** tabs, visually separated from page actions.

### Monitor and recipient editor refinements
- Added concise explanations to the four core schedule values and fixed label/help line breaks for both location-aware check-in options.
- Matched the monitor save bar width to the editor, enlarged the new text-document editor and aligned both document-creation actions, folded the safety-contact authority explanation into its selectable policy card, and converted recipient document assignments to compact responsive cards.
- Added source regressions for the shared help treatment, primary action, tabs, editor sizing, safety copy, and recipient document grid. No database migration is required.

### Optional recovery-readiness guidance
- Added a compact **Recovery readiness** overview under **Profile → Account security** whenever TOTP is enabled. It warns when no passkey is registered or only three recovery codes remain, while showing a ready state when both alternatives are healthy.
- Documented the supported recovery routes: an available passkey, an unused recovery code, or restoration of the database together with its matching `.env`. Pulse deliberately adds no email or security-question two-factor bypass, and none of these optional authentication methods is made mandatory.

## 1.2.6 - 2026-08-16

### Inline-first recipient documents and media
- Redesigned authenticated recipient document cards so safely previewable material can be read, viewed, played, or expanded directly inside Pulse while every available file retains an explicit **Download** action.
- Added on-demand same-origin readers for PDF, Markdown, plain text, formatted CSV, and formatted JSON; native previews for GIF, JPEG, PNG, WebP, and AVIF; and standard browser controls for supported audio and video without autoplay or an external player/preview service.
- Added authenticated, no-store, chunked private-file streaming with validated single HTTP byte ranges so media and PDF readers can seek without exposing storage paths. MP3 and H.264/AAC in MP4 are the recommended browser-compatibility targets.
- Applied the same inline presentation to the owner-only recipient portal preview while keeping its downloads and destructive portal actions disabled.

### Upload policy and monitor-editor clarity
- Expanded the stock upload MIME policy with common browser-compatible image, audio, video, Markdown, CSV, and JSON formats. Upgrades expand only the exact legacy stock allowlist; deliberately customized administrator policies remain unchanged.
- Added a visible maximum-size and preview-capability hint beside the monitor upload field. Word, OpenDocument, RTF, HTML, SVG, unknown, and potentially active formats remain download-only.
- Mark document cards with a textual **Unsaved changes** badge, warning border, emphasized per-document save button, and persistent **Documents** tab warning as soon as their title, description, or text changes. Reverting all fields clears the warning.
- Returned the monitor-wide **Save changes** bar to normal page flow at the bottom of the editor instead of keeping it sticky while scrolling.
- Updated localized interface text, documentation, CSP directives, source/unit regression coverage, and release metadata. No database migration is required.

## 1.2.5 - 2026-08-16

### TOTP authentication database hotfix
- Fixed valid authenticator codes being consumed and then reported as invalid. The previous multi-table update changed both the replay counter and method metadata, but accepted the code only when PDO reported exactly one affected row; MySQL can report both changed rows.
- Split counter consumption and last-used metadata into separate statements inside one transaction. The conditional counter update remains atomic and replay-safe, while its one-row result can now be evaluated reliably.
- No database migration or TOTP re-enrollment is required. A code attempted on the previous build may already have been consumed, so use a newly generated code after updating and after any active five-minute login throttle has expired.

## 1.2.4 - 2026-08-16

### TOTP first-login hotfix
- Fixed an enrollment handoff that treated the successful setup-verification code as an already-used login code. Logging out while that same 30-second code was still visible could therefore reject it as a replay; repeated attempts could then activate the five-minute TOTP throttle and temporarily reject later codes as well.
- TOTP setup now proves that the authenticator and Pulse share the same secret without consuming a login time step. Replay protection begins with the first actual TOTP authentication and remains atomic for every later authentication.
- Made the attempt that reaches the rate limit immediately display the throttling message instead of showing one final generic invalid-code message. No database migration is required.

## 1.2.3 - 2026-08-16

### Optional authenticator-app two-factor authentication
- Added optional RFC 6238-compatible TOTP under **Profile → Account security**. Enrollment requires the current password and a successful six-digit test code before the method becomes active.
- Password sign-ins now request a current authenticator code when the account has enabled TOTP, with 10 single-use recovery codes as an offline fallback. Passkey sign-in remains a complete phishing-resistant authentication and does not require a subsequent TOTP code.
- Rendered the enrollment QR code locally in the authenticated browser with a vendored MIT-licensed QR generator, so the TOTP provisioning URI is never sent to an external QR service. A manual Base32 setup key remains available.

### Credential and recovery protection
- Encrypted TOTP secrets at rest with an installation-specific AES-256-GCM key stored in `.env`; fresh installations generate the key automatically, while upgraded installations create it only when TOTP setup begins.
- Stored recovery codes only as installation-keyed hashes, displayed plaintext codes only once in the authenticated session, and required password plus TOTP/recovery re-authentication before regenerating codes or disabling TOTP.
- Added bounded clock skew, atomic time-step replay prevention, one-time recovery consumption, account- and network-scoped attempt throttling, security audit events, localized UI, RFC interoperability tests, migration `006_totp_two_factor_authentication.sql`, and Pulse 1.2.3 release metadata.

## 1.2.2 - 2026-08-16

### Form and portal-message refinements
- Increased the responsive minimum width of owner and contact email fields so wide screens use fewer, more comfortable columns while smaller screens continue to reflow safely.
- Preserved a recipient's personal portal message as a reusable draft when **Use a personal portal message for this recipient** is disabled. The separate enabled state now determines whether future portal releases include the saved message.
- Clarified that recipient portal availability applies to portals created by the monitor currently being edited.

### Fixed branding and editor placeholders
- Removed the `{app}` placeholder from all editor help and replaced it with the literal name **Pulse** in every built-in localized mail template. Existing custom templates containing `{app}` remain compatible.
- Added migration `005_recipient_portal_message_state.sql`, updated the reference schema, translations, documentation, and regression coverage, and advanced the release metadata to Pulse 1.2.2.

## 1.2.1 - 2026-08-16

### Redundant, separately checked email addresses
- Added four responsive email-address slots for the owner and for every contact. Each slot has its own **I checked this email address carefully** state; contacts can now be saved without checking an address, while unchecked addresses remain excluded from mail delivery.
- Added clear per-address and per-person status indicators to Contacts, monitor recipient and safety-contact lists, recipient details, and Profile. Any stored owner address can be used to sign in, while owner notifications and mail tests go only to checked addresses.
- Queue a separate message for every checked owner, safety-contact, or recipient address. Safety requests and recipient releases snapshot all checked addresses so later contact edits cannot change an active escalation; recipient access codes are likewise sent independently to every released address.
- Kept lifecycle semantics person-based: the first successful redundant delivery advances the relevant recipient or safety-contact state, while failures at one mailbox do not prevent attempts to the others.

### Recipient-only personal portal messages
- Removed the monitor-wide **Default personal portal message** from **Messages & content → Portal page**. The monitor-wide language selector, Page introduction, and portal availability remain unchanged.
- Made personal portal content exclusively recipient-specific. The authenticated portal shows its personal-message card only when **Use a personal portal message for this recipient** is enabled and a non-empty message has been saved.
- Reworded the recipient editor guidance around a personal or goodbye message and added validation that prevents an enabled-but-empty personal message.

### Database, documentation, and verification
- Added migration `004_multiple_email_addresses.sql`, including bounded owner/contact columns and immutable safety-request and recipient-delivery address snapshot tables. Existing primary owner addresses are retained as checked, and existing release rows receive their historical address snapshot.
- Updated the reference schema, user/security/architecture/tutorial/audit documentation, translations, version metadata, and source/unit regression coverage for Pulse 1.2.1.

## 1.2.0 - 2026-08-16

### Official Pulse 1.2 release
- Promoted the stabilized 1.1.6–1.1.10 work into the official 1.2 line: optional location-aware check-ins, immutable recipient location snapshots, the compact location table and on-demand inline map, tabbed Profile navigation, passkey reliability improvements, and the owner-only recipient portal preview.
- Kept location recording and recipient sharing as separate per-monitor opt-ins. Portal maps still make no OpenStreetMap tile request until deliberately revealed, while exact Pulse points and overlays remain browser-local.
- Retained the complete upload-overwrite update workflow and existing ordered migrations; no additional database migration is required for 1.2.0.

### Release documentation and audit
- Normalized current-release documentation from Pulse 1.1 to Pulse 1.2 while preserving historical changelog and migration references.
- Reframed the roadmap from the completed 1.2.0 baseline, leaving optional TOTP and recovery-policy work as later milestones rather than hidden release requirements.
- Expanded the release audit guide with owner-only portal-preview checks and explicit network, interaction, privacy, and responsive-layout checks for the inline location map.

## 1.1.10 - 2026-08-16

### Inline on-demand location map
- Moved the interactive map into **Last known check-in locations**, directly below the compact location table. **Show locations on map** expands it in place, and **Hide map** collapses it again without opening another browser tab.
- Preserved the deliberate privacy boundary: the authenticated portal initially makes no OpenStreetMap tile request. Tile loading begins only after the user reveals the map, while exact points, accuracy areas, details, and the chronological line continue to be rendered locally.
- Applied the same inline behavior to the owner-only recipient portal preview and removed the now-unneeded dedicated recipient and preview map routes.

### Scenario-based monitor tutorial
- Rewrote `docs/MONITOR_TUTORIAL.md` around two concrete uses: a cautious long-term “Am I still alive?” document-delivery monitor and a daily location-aware adventure-travel monitor.
- Added focused guidance for recipients, safety contacts, documents, timing, location permission, trail length, portal preview, rehearsals, and the limits of email-based escalation and discrete location points.
- No database migration or third-party JavaScript library is required.

## 1.1.9 - 2026-08-16

### Authenticated interactive location map
- Replaced the surrounding-area OpenStreetMap link with **View locations on map**, which opens a dedicated Pulse map page in a new tab only after deliberate recipient action.
- Added mouse, touch, wheel, button, and keyboard navigation; **Fit** returns to all recorded points. Numbered check-in nodes open their location, timestamp, reported accuracy, and individual OpenStreetMap link.
- Draws reported accuracy areas and a straight chronological line between the immutable released points. The interface clearly distinguishes that line from continuous tracking or proof of the route actually travelled.
- Added the same map to the owner-only recipient portal preview. The preview route requires the logged-in owner and a recipient lookup scoped to that owner; the real map requires the same active token-bound recipient session as the authenticated portal.

### Tile privacy and policy boundaries
- The authenticated portal table itself still makes no map request. Opening the dedicated map loads only the OpenStreetMap raster tiles intersecting the current viewport, with ordinary browser caching, visible attribution, and no background prefetch.
- Exact Pulse points, details, accuracy areas, and connecting lines are rendered locally and are not included in tile requests. OpenStreetMap receives the displayed tile area, the Pulse site origin, and normal browser connection information, but not the token-bearing portal URL.
- Updated the content-security and referrer policies for the configured tile origin while retaining `rel="noreferrer"` on direct external location links.
- No database migration or third-party JavaScript library is required.

## 1.1.8 - 2026-08-16

### Compact recipient location history
- Moved **Last known check-in locations** below the portal document section and replaced the tall numbered list and embedded map with a compact table containing **Location**, **Accuracy**, and **Timestamp** columns.
- Kept each recorded location as an explicit OpenStreetMap link and added one **Open area in OpenStreetMap** action covering all released points. Standard OpenStreetMap permalinks cannot reproduce an arbitrary Pulse point sequence as a path, so the interface no longer suggests that the surrounding-area link shows the travelled route.
- Removed automatic OpenStreetMap tile loading from recipient portals and removed the tile host from Pulse's content-security policy. Merely viewing an authenticated portal now makes no third-party map request; coordinates leave Pulse only when the recipient deliberately follows an OpenStreetMap link.

### Portal preview and update guidance
- Replaced **Back to recipient editor** in a new-tab portal preview with **Close preview**, preventing a second recipient-editor tab from being created.
- Corrected the update guide to match the verified installer behavior: a full release archive may be uploaded over an initialized installation, where `public/install.php` verifies the existing account and attempts to remove itself without recreating configuration or users.
- No database migration is required for this release.

## 1.1.7 - 2026-08-16

### Owner-only recipient portal preview
- Added **Preview recipient portal** to the recipient editor’s Overview tab. It opens the future authenticated recipient portal in a new tab using the currently saved recipient language, portal text, assigned documents, and location-sharing settings.
- Kept the preview behind the normal owner login and monitor-recipient ownership checks. Sharing its URL does not grant access, and another Pulse account cannot use the recipient ID to view it.
- Preview requests do not create releases, portal tokens, access codes, email, audit activity, or recipient sessions. Document downloads and permanent portal closure are visibly disabled.
- Current assigned text and image documents are represented in the portal layout, and current check-in locations appear only when portal location sharing is enabled for the monitor.

## 1.1.6 - 2026-08-16

### Optional location-aware check-ins
- Added a per-monitor **Record location during check-ins** option. Enabling it asks the current device for browser geolocation permission; browsers may retain that grant, while Pulse safely retries at check-in time when necessary.
- Location collection is one-shot and never continuous. A denied, revoked, timed-out, or unavailable position never blocks the check-in.
- Stored check-ins can include validated coordinates, reported accuracy, and an accuracy-appropriate approximate address resolved through OpenStreetMap Nominatim. Owner activity history links recorded locations to OpenStreetMap.
- Added a separate per-monitor opt-in to release the most recent 1–20 check-in locations to authenticated recipients. The selected chronological trail is copied into the immutable recipient release, so an existing portal cannot reveal later movements.
- Added an on-demand OpenStreetMap trail view in the authenticated recipient portal. The map loads tiles only after the recipient selects **Load map**, connects discrete check-in points with straight lines, and clearly states that it is neither continuous tracking nor an actual route.

### Profile navigation
- Reorganized Profile into the three current responsive tabs: **Profile data**, **Account security**, and **Change password**.
- Kept validation, passkey-management, and password-change redirects on their originating tabs.

### Database and verification
- Added migration `003_check_in_locations.sql`, updated the current reference schema, security policy, translations, documentation, and source/unit regression coverage.
- Added a dedicated 1.1.5-to-1.1.6 update package that excludes the fresh-install entry point, `.env`, and mutable private data; clarified that the full source archive is not a drop-in update archive.

## 1.1.5 - 2026-08-16

### Passkey login reliability
- Separated passkey sign-in controls from the native username/password form so Safari cannot accidentally submit the password form while a WebAuthn ceremony is completing.
- Added a client-side passkey-login lock that suppresses native password-form submission until passkey authentication either fails/cancels or redirects successfully.
- Changed successful passkey navigation to replace the login page in browser history.
- Added a narrow server-side safety net for an already-authenticated stale `POST /login`: the stale request remains rejected as a login attempt but is redirected to the authenticated destination instead of showing the CSRF 419 page. CSRF rotation and normal invalid-CSRF handling remain unchanged.

## 1.1.4 - 2026-08-16

### Passkey registration UX
- Replaced the browser's raw WebAuthn `InvalidStateError` during passkey registration with a friendly explanation that an existing Pulse passkey is already available on the device.
- When the account has exactly one registered passkey, the message identifies it by its Pulse label; with multiple registered passkeys the message remains generic because WebAuthn does not reveal which excluded credential matched.
- Updated passkey guidance to explain that password managers such as iCloud Keychain may synchronize one passkey across multiple devices, so users should verify availability rather than create a duplicate credential on every device.

## 1.1.3 - 2026-08-16

### Authentication UI
- Moved the normal passkey sign-in action below the username/password fields on the login page.
- After successful passkey registration, Profile now reloads immediately so the new credential appears in the passkey list without a manual refresh.

### Monitor editor save flow
- Replaced the two-step monitor/message save UX with one visible **Save changes** action. Dirty Messages & content subsections are saved first automatically, followed by the monitor settings.
- Multiple edited message subsections are saved in one pass, and leaving the page with unsaved message edits triggers the browser's unsaved-changes warning. A no-JavaScript **Save messages** fallback remains available.
- Validation failures during the combined save stay in the editor and preserve other unsaved message subsections instead of navigating away mid-save.

### Owner reminder templates
- Removed the hidden automatic quick-check-in paragraph that was appended to built-in owner reminder mail.
- Added `{quickcheckin}` as an explicit optional block placeholder in the built-in owner due/reminder templates. It expands to the localized Markdown quick-check-in link when globally enabled and to nothing when disabled.
- Kept `{quickurl}` for custom link wording; it continues to fall back to the normal `{url}` login URL when quick check-in is disabled.

### Documentation
- Expanded the passkey/quick-check-in guidance to present quick check-in as the recommended low-friction routine and to stress preparing and testing a passkey on every device that may be used to respond to reminders.

## 1.1.2 - 2026-08-16

### Passkeys and account security
- Added an extensible account-security method layer, with passkeys as the first additional authentication method and room for later second-factor methods such as TOTP or recovery codes.
- Added passkey registration/removal under Profile → Account security and passkey sign-in on the normal Pulse login page. Registration and removal require the current account password.
- WebAuthn ceremonies use fresh single-use challenges, require user verification, bind credentials to the configured Pulse RP ID/origin, store only credential public material, and verify assertions with OpenSSL.

### Quick check-in
- Added the global Administration option **Enable passkey quick check-in**. Reminder links are generated only when this option is enabled.
- Quick-check-in email links are non-authenticating, hashed server-side, expiring, single-use pointers bound to the monitoring cycle that generated them. The actual check-in requires passkey authentication or the normal password-login fallback.
- A successful quick check-in confirms **all active monitors**, matching Pulse's existing global check-in action, and records the authentication source in audit context.
- Passkey login reached through the password-fallback page also completes the pending quick check-in instead of merely signing in.

### Owner reminder mail
- Added one Markdown-capable custom template per monitor for the initial owner due notice and one for follow-up owner reminders. Built-in fallback templates remain localized to the owner notification language; custom overrides are used exactly as written.
- Added the standard **Edit / Preview** editor and collapsible **Show current default template** disclosure for both owner-mail templates.
- Added placeholders including `{url}` for the normal Pulse login and `{quickurl}` for quick check-in. When quick check-in is disabled globally, `{quickurl}` safely resolves to `{url}`.

### Markdown
- Added CommonMark-style hard line breaks: two spaces at the end of a source line now render as `<br>` in portal content and HTML email.

### Verification
- Updated the current reference schema and migration/source regression checks for the post-1.0 migration line.
- Added security UI styling and regression coverage for owner templates, quick-check-in wiring, and Markdown hard breaks.

## 1.1.0 - 2026-08-15

### Markdown content
- Added dependency-free Markdown support for Pulse-created text documents, personal portal messages, recipient notification bodies, and safety-contact mail bodies.
- Added a shared **Edit / Preview** editor. Preview renders unsaved source server-side through the same safe Markdown parser used by recipient pages.
- Supported headings, bold, italic, ordered/unordered lists, links, blockquotes, horizontal rules, inline code, and fenced code blocks. Raw HTML is escaped and unsafe link schemes are rejected.
- Recipient portal personal messages and text-document previews now render Markdown. Text documents can be opened as rendered pages while downloads preserve the original Markdown source as `.md` files, including inside **Download all** archives.

### HTML email
- SMTP delivery now emits `multipart/alternative` messages. Each queued Markdown-capable body produces a readable `text/plain` alternative and a sanitized `text/html` alternative.
- HTML mail uses conservative inline CSS and requires no external stylesheet or runtime Composer dependency.
- Existing plain-text templates remain valid Markdown source and continue to render as ordinary paragraphs, so no database migration is required.

### Verification
- Added Markdown renderer and multipart SMTP tests, localization strings, documentation updates, and release version 1.1.0.

## 1.0.0 - 2026-08-15

### Stable release
- Established Pulse 1.0.0 on the audited fresh-install database baseline.
- Added **Last successful cron run** to Administration → Cron. Successful combined web-cron and CLI `notifications:run` executions record their completion timestamp.
- Added Cron-tab configuration warnings when no successful run has ever been recorded or when the last successful run is more than 24 hours old.
- Clarified the debug **Force due now** confirmation so it explains that the due notice is queued by the next cron run rather than immediately by the button itself.
- Re-audited and substantially rebuilt the shipped 1.0 documentation against the final interface and runtime behavior: corrected the seven Monitor Editor tabs and five Recipient Editor tabs, current recipient-email `{url}` requirements, archive/delete rules, portal snapshot behavior, Administration, cron semantics, and the stable update model.
- Simplified the user-facing guides and expanded missing end-to-end workflows, including first setup, rehearsal, recipient delivery, safety contacts, history, mail recovery, and operational checks.
- Clarified the actual deployment constraint: Pulse 1.0 expects `public/` at the root of its host or virtual host; URL-prefix deployments such as `/pulse/` are not supported by the built-in router.
- Added a repeatable `docs/AUDIT_GUIDE.md` for installation, lifecycle, portal-security, queue/concurrency, integrity, localization, and release verification.

### Verification
- Final source, schema, configuration, translation, security, documentation, and package checks completed against the clean 1.0 baseline.

## 0.9.10 - 2026-08-15

### 1.0 baseline cleanup
- Squashed the complete pre-release database history into a single `001_initial_schema.sql` containing the current Pulse schema.
- Removed pre-release migration baselining and compatibility branches from the migration runner. Future schema changes will build forward from the 1.0 baseline.
- Removed obsolete migration-specific references, pre-release compatibility guidance, and now-dead delivery-retention cleanup code from the current baseline.
- Reset the current documentation to describe the clean 1.0-era installation, configuration, architecture, and update model.
- Added mail-deliverability guidance recommending safe-sender/allowlist or whitelist entries for the configured Pulse sender, alongside normal SPF/DKIM/DMARC setup.

### Data integrity
- Monitors with recipient release history can no longer be deleted. Released delivery/history is preserved; use the monitor lifecycle/archive model instead.
- Contacts that are still assigned as a recipient or safety contact on any monitor can no longer be deleted until those assignments are removed or changed.
- Added restrictive foreign-key rules to the baseline schema as a database-level backstop for current monitor/contact assignments and released monitor history.

### Interface
- Further refined Monitor Editor → Recipients so language, email-text source, and document count share the available metadata width naturally instead of leaving a trailing empty column.
- Contact and monitor action menus now reflect the new deletion guards.

### Development
- Version bumped to 0.9.10.
- This build establishes the database/source baseline intended for the final fresh-install audit before Pulse 1.0.0.
