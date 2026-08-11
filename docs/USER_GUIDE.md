# Pulse user guide

## Current scope

Pulse 0.5.0 lets you:

- sign in and update your profile
- create and edit trusted contacts and confirm that you checked their addresses
- configure monitors in four focused editor tabs
- prepare a default delivery message and optional recipient-specific messages
- create editable text documents or upload approved file types
- assign text and file documents to individual monitor recipients
- see whether monitors are checked in, awaiting check-in, overdue, escalated, or paused
- confirm every active monitor with one check-in
- pause and resume individual monitors with explicit actions
- review current monitor state and recent lifecycle activity on the dashboard

Automatic reminder mail, escalation, contact notifications, and recipient document access are not active yet.

Uploaded files are private from normal website visitors, but files, messages, and editable text documents are not encrypted at rest in this version. Do not store your final highly sensitive material until the encrypted-storage release is complete.

## Contacts

A contact is someone who may later receive a message or document. Create contacts before assigning them to monitors.

Editing a contact updates the shared contact record. Removing a contact also removes that contact’s monitor assignments through database relationships.

Pulse requires you to confirm that you carefully checked a contact's email address. This is not consent or remote verification: the checkbox records only your review. Pulse does not send an invitation, approval request, or verification email. If the address resembles a common domain typo, Pulse displays a suggestion but leaves the decision to you.

Contacts created before 0.4.0 initially show **Not yet checked**. In a monitor editor, open **Recipients**, select **Check address** beside the contact, review the address, tick the confirmation box, and save. Pulse returns to the same monitor tab and records the new status.

## Monitors

A monitor describes how frequently you intend to confirm that you are active. Its editor contains four sections:

1. **Schedule** — monitor identity, check-in interval, response window, and reminder settings
2. **Recipients** — contacts assigned to this monitor and their address-check state
3. **Messages & documents** — delivery wording and recipient document assignments
4. **Review & activation** — a configuration summary, warnings, and the paused state

The sticky save action stores schedule and recipient selection together. **Cancel** returns to the monitor overview without saving changes to those settings. Messages and individual documents have their own explicit save actions. Pause and resume are immediate runtime actions and are not part of the settings form.

Select a monitor's linked title in the overview to open its editor.

The monitor overview focuses on runtime state:

- **Checked in** — the current scheduled cycle has a future due time
- **Awaiting check-in** — the due time arrived and Pulse is waiting for confirmation
- **Overdue** — the notification worker recorded that every configured owner reminder was sent without a response
- **Escalated** — recipient notification or delivery actually began
- **Paused** — no confirmation is currently expected

Pulse 0.5.0 does not yet run the reminder and notification engine. It can move scheduled cycles to **Awaiting check-in**, but it does not pretend to have sent reminders. Consequently, a normal 0.5.0 installation will not move a cycle to **Overdue** or **Escalated** until the later notification worker is connected.

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

Recent lifecycle activity records check-ins, due-state changes, pauses, resumes, overdue transitions, and escalations. Times are stored in UTC and displayed in the configured local timezone.

## Messages

The default subject and message apply to every assigned recipient. Enable a personal override for a recipient only when that person should receive different wording. A personal message requires both its own subject and body.

Removing a recipient from a monitor also removes that assignment's personal message and document links. The contact itself remains available elsewhere in Pulse.

## Documents

Open **Messages & documents** in a monitor editor to create text documents, upload files, and assign either type to one or more recipients.

Editable text documents are stored in the database. They can be changed directly in the editor. Uploaded document contents are stored as server files outside the public web directory; the database contains their safe storage identifiers and metadata rather than a file BLOB.

The default upload policy accepts PDF, RTF, OpenDocument Text, Word `.docx`, JPEG, PNG, and plain text files up to 25 MiB. Administrators can change the size and MIME allowlists in `.env`.

Pulse detects the file type from the uploaded content rather than trusting the filename. Files are renamed and stored outside the public web directory. Downloads always pass through authentication and ownership checks.

Deleting a document removes its database record and stored file. Deleting a monitor removes all files attached to it.

## Passwords and sessions

The default minimum password length is 12 characters. Sessions expire after inactivity and after an absolute lifetime; these limits are configurable by the administrator.

Repeated failed sign-ins are temporarily blocked. The response does not reveal whether an email address belongs to an account.

## Languages

English and German are available from the footer. Language changes use the same security protection as other state changes and return only to a local Pulse page.

## Health checks

`/health` is a minimal public liveness endpoint and returns only a status value. Signed-in users can access `/health/readiness` for database and storage readiness.
