# Pulse user guide

## Current scope

Pulse 0.3.1 lets you:

- sign in and update your profile
- create and edit trusted contacts
- create monitors and assign contacts
- upload approved document types to a monitor
- choose document recipients from that monitor’s contacts
- see whether monitors are scheduled, due, or paused
- manually confirm a due monitor

Automatic reminder mail, escalation, contact notifications, and recipient document access are not active yet.

Uploaded files are private from normal website visitors, but they are not encrypted at rest in this version. Do not upload your final highly sensitive documents until the encrypted-storage release is complete.

## Contacts

A contact is someone who may later receive a message or document. Create contacts before assigning them to monitors.

Editing a contact updates the shared contact record. Removing a contact also removes that contact’s monitor assignments through database relationships.

## Monitors

A monitor describes how frequently you intend to confirm that you are active. Its editor contains future reminder/escalation settings as well as its contact assignments.

The monitor overview focuses on runtime state:

- **Scheduled** — no confirmation is currently required
- **Due** — the monitor can be confirmed now
- **Paused** — no confirmation is currently expected

The displayed timestamps use the configured local display timezone. Storage and comparisons use UTC.

## Manual check-in

When a monitor is due, **Check in now** appears on the dashboard and monitor overview. Confirming it records the current time and schedules the next due date from the check interval.

The action is intentionally one click, but it is accepted only for a due, active monitor owned by the signed-in user.

## Documents

Open a monitor’s editor to upload documents and assign recipients.

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
