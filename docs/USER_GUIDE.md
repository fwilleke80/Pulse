# Pulse user guide

Pulse is designed for situations in which something could happen to you and the people who need to know might otherwise not be informed for some time. You might use it to make sure family, friends, or other trusted people are contacted if you die or become seriously ill, or as an additional safety measure while travelling alone, going on an expedition, or spending time somewhere where help may be difficult to reach.

You tell Pulse how often you expect to check in. As long as you continue to do so, nothing happens. If you stop responding, Pulse first tries to reach you and, if necessary, eventually contacts the people you selected and sends them the messages you prepared in advance.

For server setup, SMTP, and cron configuration, see [Installing Pulse](INSTALLATION.md).

## The basic workflow

A typical setup looks like this:

1. Add the people you may want Pulse to contact.
2. Create a monitor and choose how often you want to check in.
3. Choose the recipients for that monitor.
4. Write the message they should receive in case you stop checking in.
5. Optionally add safety contacts who get a chance to confirm that they recently heard from you before recipient notification begins.
6. Review the monitor and activate it.
7. Check in periodically from the dashboard.

Pulse can be used for anything from a low-urgency continuity reminder to a carefully planned dead man's switch. The [monitor tutorial](MONITOR_TUTORIAL.md) gives several practical examples.

## Dashboard and check-ins

The dashboard shows the current state of your monitors, when you last checked in, when the next check-in is due, and recent activity.

When at least one monitor is active, **Check in now** confirms all active monitors at once. Each monitor then starts a fresh interval using its own schedule.

You do not have to wait until a monitor is due. Checking in early simply proves that you are present now and restarts that monitor's interval from the current time.

Paused monitors are not included in a global check-in.

If a monitor has already sent a recipient notification, a later check-in still starts a new cycle but cannot recall the email that was already sent.

## Monitor statuses

Pulse uses a small set of states to show where a monitor currently is:

- **Checked in** — the monitor is active and its next due time is still in the future
- **Awaiting check-in** — the due time has arrived and Pulse is waiting for you
- **Awaiting safety contact** — your own reminder phase ended and the optional safety-contact step is in progress
- **Overdue** — all required waiting/reminder stages have completed and recipient notification is ready or being attempted
- **Escalated** — at least one recipient notification has actually been accepted for delivery by the mail server
- **Paused** — the monitor is temporarily inactive

Pulse does not mark a monitor overdue merely because time passed. Required reminder stages must also have been processed successfully.

## Creating and editing a monitor

The monitor editor is divided into five sections.

### Schedule

Choose the monitor name and the timing of your own check-in process:

- **Check-in interval** — how long after a successful check-in the next one becomes due
- **Response window** — how long you have after the due notice before the first follow-up reminder
- **Reminder interval** — time between follow-up reminders
- **Maximum follow-up reminders** — number of reminders after the initial due notice

The initial due notice is not counted as a follow-up reminder.

For example, with a two-day response window and two follow-up reminders one day apart, the owner phase lasts four days after the due time:

```text
due notice → 2 days → reminder 1 → 1 day → reminder 2 → 1 day → escalation step
```

### Recipients

Recipients are the people who receive the final monitor message if the escalation process reaches them.

Add an existing contact, then use **Configure recipient** to control that person's message, assigned documents, preview, and delivery history.

### Messages & documents

A monitor has one default recipient message. Individual recipients can override it with their own subject and body.

Documents also belong to a monitor. You can create text documents or upload supported files and assign them to selected recipients.

### Escalation

Choose what happens after your own reminders have gone unanswered:

- **Direct recipient notification** — Pulse proceeds directly to the recipients
- **Safety-contact confirmation** — Pulse first asks selected trusted people whether they have had recent direct contact with you

The safety-contact option is useful when an ordinary missed check-in should not immediately trigger the final message.

### Review & activation

Review the complete configuration and resolve any warnings before relying on the monitor. You can also pause or resume the monitor.

## Contacts

A contact is a reusable person record. The same person can be a recipient on one monitor, a safety contact on another, or both.

For each contact, store:

- name
- email address
- Pulse interface language

Pulse asks you to confirm that you personally checked the email address. This is only a local confirmation that you reviewed the address; Pulse does not send a verification message to the contact.

If Pulse recognizes a likely typo in a common email domain, it can display a suggestion. You remain responsible for deciding whether the address is correct.

Editing a contact updates the reusable contact record. Removing a contact also removes its assignments to monitors.

## Recipient messages

Each monitor can have a custom default message for its recipients. A recipient can either use that monitor default or have a personal subject and body. If the monitor default is left empty, Pulse uses its built-in localized recipient message instead. The editor shows that fallback text so it is never hidden.

Custom recipient subject/body templates support `{app}`, `{name}`, `{owner}`, and `{monitor}`. The editor lists the supported placeholders beside the text fields. The recipient page shows the exact expanded message Pulse will queue. Pulse does not silently add a second explanatory wrapper around custom text.

A useful message should make sense on its own. Consider explaining:

- who configured it
- why the recipient is receiving it
- what they should do next
- what they should verify independently before taking consequential action

Avoid passwords, recovery keys, or other secrets in email. Once a recipient email is sent, it also exists at the mail provider and in the recipient's mailbox.

When Pulse prepares a recipient release, it takes a snapshot of the recipient and message. Later edits affect future releases, not a message that has already been queued or sent.

## Safety contacts

A safety contact is an optional extra step before recipient notification.

After your own reminder phase ends, Pulse emails the selected safety contacts. A safety contact can then explicitly choose one of two responses:

- **Confirm recent direct contact** — indicates that they recently had direct contact with you
- **Cannot confirm** — records that they cannot make that confirmation

Simply opening the email link does nothing. The safety contact must submit a deliberate response on the Pulse page, which prevents mail scanners or link previews from accidentally confirming anything.

You can require more than one confirmation. For example, with three safety contacts you might require two confirmations before Pulse postpones the monitor.

A qualifying confirmation closes the current cycle and schedules a fresh one using the configured postponement period. Setting postponement to `0` reuses the monitor's normal check-in interval.

A safety contact cannot:

- read the final recipient message
- see assigned documents
- trigger recipient notification early

If the required confirmation is not reached before the safety-contact stage ends, Pulse proceeds toward recipient notification.

### Custom safety-contact email text

On **Safety & escalation**, you can replace the default subject and body used for the first safety-contact email and for later reminders. The templates support `{app}`, `{name}`, `{owner}`, `{monitor}`, and `{url}`; reminder text also supports `{number}` and `{total}`. Leave both fields for a mail type empty to use Pulse's localized default wording. The editor displays that default subject and body in the current interface language. The contact's **Pulse interface language** is still used for the safety-confirmation page and, later, the recipient portal.

## Notifications and failed mail

Owner reminders, safety-contact requests, and recipient messages all use Pulse's mail queue.

Open **Profile → Notifications** to:

- see whether mail is enabled
- send a test notification
- review pending, sent, and failed mail
- retry permanently failed notifications after fixing the cause

A failed email does not count as successfully delivered. Pulse shows a warning rather than pretending the notification happened.

Your profile has its own **Notification language** for Pulse-authored owner due notices, reminders, and test mail. Contacts have a **Pulse interface language** for Pulse-owned pages such as safety confirmation and the future recipient portal. That language also selects localized Pulse fallback text when safety-contact or recipient mail is left at its built-in default.

If SMTP settings are changed, send another test before relying on the system.

## Pause and resume

Use **Pause** when you temporarily do not want a monitor to expect check-ins—for example during a planned period when the monitor should not run.

Pausing cancels the current active cycle and removes its due date. The monitor is also excluded from **Check in now**.

**Resume** starts a fresh schedule from the moment you resume it. A long pause therefore does not cause the monitor to become immediately overdue.

## History

Pulse records important lifecycle events such as:

- check-ins
- due-state changes
- reminders
- safety-contact requests and confirmations
- overdue transitions
- recipient delivery
- pauses and resumes

The dashboard shows the most recent activity. Use **View complete activity** for the full history.

## Documents

Pulse supports two kinds of monitor documents:

- editable text documents stored in the database
- uploaded files stored privately outside the public web directory

You can assign documents to individual recipients from the recipient configuration page or document form. Uploaded files have a separate display title and optional short description. Both can be edited later without renaming or moving the private stored file.

The default upload policy accepts PDF, RTF, OpenDocument Text, Word `.docx`, JPEG, PNG, and plain text files up to 25 MiB. The server administrator can change the upload limits and MIME allowlist in `.env`.

Pulse inspects the uploaded content rather than trusting the filename. Stored files receive internal names and are not served directly from the public web directory.

### Important current limitation

Recipient document delivery is **not active** in Pulse 0.7.3. Assigning a document to a recipient prepares that relationship for the future secure document portal, but current recipient emails contain:

- no attachment
- no document content
- no document download link

Uploaded files, editable text documents, and recipient messages are also not encrypted at rest yet. Do not use the current release as storage for final highly sensitive secrets or cryptographic recovery material.

## Languages

Pulse provides English and German interfaces.

The interface language, your owner-notification language, and each contact's Pulse interface language are separate. Changing the language in the footer does not change either stored language setting.

## Health checks

`/health` is a minimal public liveness check.

Signed-in users can also open `/health/readiness` to verify that important resources such as the database and storage are ready.

