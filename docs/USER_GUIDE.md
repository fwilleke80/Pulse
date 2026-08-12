# Pulse user guide

## Current scope

Pulse 0.7.0 lets you:

- sign in and update your profile
- create and edit trusted contacts and confirm that you checked their addresses
- configure monitors in five focused editor tabs
- prepare a default delivery message and optional recipient-specific messages
- create editable text documents or upload approved file types
- assign text and file documents to individual monitor recipients
- see whether monitors are checked in, awaiting check-in, overdue, escalated, or paused
- confirm every active monitor with one check-in
- pause and resume individual monitors with explicit actions
- review current monitor state and recent lifecycle activity on the dashboard
- receive an immediate owner email when a check-in becomes due, followed by configured reminders after the response window closes
- test SMTP delivery and inspect or retry failed notifications from the profile page
- choose a separate notification language for yourself and every contact
- send actual localized recipient notification emails containing the configured message
- choose direct recipient notification or an optional safety-contact gate per monitor
- require one or more checked safety contacts to confirm recent direct contact before a monitor is postponed
- configure each monitor recipient on a dedicated page with personal wording, document assignments, preview, and delivery history

Recipient message email is active in 0.7.0. Document release and recipient document access are not active: recipient emails contain no attachment, document content, or document-access link. Document assignments prepare the data model for a later secure portal.

Uploaded files are private from normal website visitors, but files, messages, and editable text documents are not encrypted at rest in this version. Do not store your final highly sensitive material until the encrypted-storage release is complete.

## Contacts

A contact is someone who may later receive a message or document. Create contacts before assigning them to monitors.

Editing a contact updates the shared contact record. Removing a contact also removes that contact’s monitor assignments through database relationships.

Pulse requires you to confirm that you carefully checked a contact's email address. This is not consent or remote verification: the checkbox records only your review. Creating, editing, or checking the address does not send an invitation, approval request, or verification email. Pulse can later send that address a recipient or safety-contact message only when an active monitor reaches the configured stage. If the address resembles a common domain typo, Pulse displays a suggestion but leaves the decision to you.

Contacts created before 0.4.0 initially show **Not yet checked**. Open **Contacts**, edit the contact, review the address, tick the confirmation box, and save. The dedicated monitor-recipient page links back to that reusable contact record when a correction is needed.

Each contact has a **Notification language**. Choose the language that contact should receive for safety or recipient mail, regardless of the language currently selected in your browser. Existing contacts fall back to the server's `PULSE_DEFAULT_LOCALE` until you save an explicit choice.

## Monitors

A monitor describes how frequently you intend to confirm that you are active. Its editor contains five sections:

1. **Schedule** — monitor identity, check-in interval, response window, and reminder settings
2. **Recipients** — a compact recipient list, delivery state, and links to dedicated recipient pages
3. **Messages & documents** — the default delivery wording and monitor document management
4. **Escalation** — direct delivery or optional safety contacts, confirmation quorum, and safety timing
5. **Review & activation** — a configuration summary, warnings, and the paused state

The sticky save action stores schedule and safety-gate settings together and returns to whichever editor tab was active. **Cancel** returns to the monitor overview without saving changes to those settings. Recipient configuration, messages, and individual documents have their own explicit save actions. Pause and resume are immediate runtime actions and are not part of the settings form.

Select a monitor's linked title in the overview to open its editor.

The monitor overview focuses on runtime state:

- **Checked in** — the current scheduled cycle has a future due time
- **Awaiting check-in** — the due time arrived and Pulse is waiting for confirmation
- **Awaiting safety contact** — the owner phase completed and Pulse is waiting for the optional safety gate
- **Overdue** — the notification worker recorded that every configured owner reminder was sent without a response
- **Escalated** — SMTP accepted at least one recipient notification
- **Paused** — no confirmation is currently expected

The cron scheduler sends an immediate owner notification when the monitor becomes due. The configured response window then gives you time to check in. If it closes without a check-in, Pulse sends reminder 1 and follows the configured reminder interval for any remaining follow-ups. **Maximum follow-up reminders** does not include the initial due notice. The owner phase completes only after the due notice and all configured reminders were accepted by SMTP and the final response/reminder interval elapsed.

With **Direct recipient notification**, Pulse then marks the cycle **Overdue** and stages immutable recipient emails. With **Safety-contact gate**, it first enters **Awaiting safety contact** and delivers the configured requests. If the confirmation quorum is reached, the cycle is postponed. Otherwise, it becomes **Overdue** only after the complete safety response/reminder window and every required safety message were delivered.

The initial email states the exact response-window length, its deadline, and the maximum follow-up count. Its sign-in URL appears on a separate line in both notification languages.

If an owner, safety-contact, or recipient message exhausts all delivery attempts, Pulse displays a red notification-delivery warning. It does not pretend that an email was delivered. Open **Profile → Notifications** to see failed jobs and queue them for another attempt after correcting the SMTP problem.

If recipient staging is blocked because there are no recipients, an address is unchecked, or an effective message is incomplete, the monitor remains **Overdue** and shows a recipient-release warning. Fix the dedicated recipient configuration; the next scheduler run tries staging again.

See the [monitor seriousness tutorial](MONITOR_TUTORIAL.md) for worked timing profiles and guidance on choosing direct delivery, a single safety contact, or a confirmation quorum.

## Safety contacts

A safety contact is a temporary gate before final recipient notification. The role is separate from being a recipient, although the same reusable contact can serve both roles.

Select safety contacts under a monitor's **Escalation** tab. Every selected address must have been checked. **Required confirmations** must be at least one and cannot exceed the number of selected contacts.

After the owner phase ends, Pulse snapshots the safety-contact name, address, and language, then queues each invitation. The safety response clock starts only after every initial invitation is accepted by SMTP. Reminder timing is measured from that shared start.

The email link opens a read-only explanation page. Automated link scanners and previews therefore cannot confirm anything. The safety contact must deliberately submit one of two responses:

- **Confirm recent direct contact** — counts toward the configured quorum; when the quorum is reached, Pulse closes the cycle and schedules a fresh one
- **Cannot confirm** — records the answer but does not accelerate recipient release; the gate still waits for its configured deadline

The confirmation checkbox explicitly states that direct contact occurred. A safety contact cannot read recipient wording, see documents, or trigger immediate escalation. The safety page uses the contact's stored notification language.

The postponement field accepts `0` to reuse the normal monitor interval. A positive value starts the next due time that many days after the qualifying safety confirmation. It is an external confirmation, not an owner check-in, and is recorded separately in lifecycle history.

## Recipient notification

Open **Recipients**, add an existing contact, then choose **Configure recipient**. The dedicated page contains:

- the reusable contact summary and a link to edit it globally
- a switch between the monitor's default message and a personal subject/body override
- document assignments for the future secure portal
- a localized preview of the actual email wrapper and configured message
- immutable queued, sent, failed, or cancelled delivery history

When a release becomes eligible, Pulse validates every recipient fail-closed. It snapshots the contact identity, checked address, notification language, localized subject, and body before queueing. Editing the recipient afterward affects only future releases; it cannot rewrite or recall the current snapshot.

The first recipient SMTP success changes the monitor from **Overdue** to **Escalated**. If one recipient succeeds and another fails, the release is partial and the monitor remains **Escalated**, because real notification already occurred. If all recipient attempts fail, no false escalation is recorded.

## Notifications

The profile page shows whether mail is enabled, pending/sent/failed queue counts, and the latest test result. **Send test notification** delivers to the current profile email using the same queue and SMTP worker as real reminders.

The **Notification language** in Profile data belongs to you as the reminder recipient. Owner reminders and tests always use that stored choice; changing the footer's interface language does not change mail language. A queued mail remains in the language in which it was created.

When mail is disabled, Pulse shows a critical warning on the Dashboard and a visibly disabled test button on the profile page. Configure the `PULSE_SMTP_*` and `PULSE_MAIL_*` values in the server's `.env` file, set `PULSE_MAIL_ENABLED=true`, then reload the profile page. The SMTP test should succeed before Pulse is relied upon for reminders.

Delivery attempts retry automatically according to the server configuration. A permanently failed message can be requeued with **Retry failed notifications**. The normal cron worker performs the new attempt.

Changing a profile email affects newly queued reminders. Messages already in the queue retain the address and content snapshot that existed when they were created.

In a non-production environment, `PULSE_DEBUG=true` exposes lifecycle test actions on the monitor overview. **Force due now** changes a checked-in monitor to **Awaiting check-in**. **Send due notification now** then sends that cycle's initial notice through the real queue and SMTP worker immediately. **Send recipient notification now** deliberately bypasses any remaining owner and safety waiting periods, snapshots the current recipient messages, and attempts real delivery after an explicit warning. Use only non-sensitive test recipients. Production always suppresses these actions.

The displayed timestamps use the configured local display timezone. Storage and comparisons use UTC.

## Global check-in

When at least one monitor is active, **Check in now** appears on the dashboard and monitor overview. One click confirms every active monitor in a single database transaction. Paused monitors are not changed.

An active monitor does not need to be due. A check-in is proof that you are present now, so each active monitor restarts its own interval from that exact UTC time. A monitor with a two-day interval becomes due in two days; a monitor with a thirty-day interval becomes due in thirty days.

If any monitor already reached **Escalated**, checking in records the late confirmation and starts its next cycle, but it cannot undo recipient notifications that were already sent. Pulse shows a warning in that case.

## Pause and resume

Use **Pause** when a monitor should temporarily expect no check-ins. Pausing immediately cancels its open cycle, records the action, and removes its active due date. Paused monitors are excluded from the global check-in.

Use **Resume** to reactivate it. Resuming counts as a fresh confirmation and schedules a new cycle from that moment. A monitor therefore cannot become instantly overdue merely because it was paused for a long time.

Pause and resume actions are available on the dashboard, monitor overview, and the editor's **Review & activation** tab.

## Dashboard and history

The dashboard shows monitor totals, the number of active monitors, and how many currently need attention. Its monitor overview includes each status, last confirmation, next due time, and pause/resume control.

The dashboard shows the latest 10 lifecycle entries. Use **View complete activity** for the complete history, shown 50 entries at a time. The history records check-ins, due-state changes, owner mail, safety requests and confirmations, recipient delivery, pauses, resumes, overdue transitions, and escalations. Times are stored in UTC and displayed in the configured local timezone.

## Messages

The default subject and message apply to every assigned recipient. Open a dedicated recipient page and enable a personal override only when that person should receive different wording. A personal message requires both its own subject and body. The preview shows the localized wrapper around the effective configured message.

Removing a recipient from a monitor also removes that assignment's personal message and document links. The contact itself remains available elsewhere in Pulse.

## Documents

Open **Messages & documents** in a monitor editor to create text documents or upload files. Assign either type from a dedicated recipient page or from the document form.

Editable text documents are stored in the database. They can be changed directly in the editor. Uploaded document contents are stored as server files outside the public web directory; the database contains their safe storage identifiers and metadata rather than a file BLOB.

The default upload policy accepts PDF, RTF, OpenDocument Text, Word `.docx`, JPEG, PNG, and plain text files up to 25 MiB. Administrators can change the size and MIME allowlists in `.env`.

Pulse detects the file type from the uploaded content rather than trusting the filename. Files are renamed and stored outside the public web directory. Downloads always pass through authentication and ownership checks.

Deleting a document removes its database record and stored file. Deleting a monitor removes all files attached to it.

Recipients cannot download or discover these documents in 0.7.0. No recipient or safety email includes a document token or URL. Continue to treat assignments as future configuration only.

## Passwords and sessions

The default minimum password length is 12 characters. Sessions expire after inactivity and after an absolute lifetime; these limits are configurable by the administrator.

Repeated failed sign-ins are temporarily blocked. The response does not reveal whether an email address belongs to an account.

## Languages

English and German are available from the footer. Language changes use the same security protection as other state changes and return only to a local Pulse page. The interface language is independent of each recipient's notification language.

## Health checks

`/health` is a minimal public liveness endpoint and returns only a status value. Signed-in users can access `/health/readiness` for database and storage readiness.
