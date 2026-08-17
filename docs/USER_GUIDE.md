# Pulse user guide

Pulse is designed for situations in which something could happen to you and the people who need to know might otherwise not be informed for some time. You might use it to make sure family, friends, or other trusted people are contacted if you die or become seriously ill, or as an additional safety measure while travelling alone or spending time somewhere where help may be difficult to reach.

You tell Pulse how often you expect to check in. As long as you continue to do so, nothing happens. If you stop responding, Pulse first tries to reach you and, depending on the monitor you configured, can ask safety contacts for confirmation and eventually notify final recipients.

Pulse is not an emergency-response service. Its timing depends on cron, email delivery, and the availability of your server. For server setup, SMTP, cron, and updates, see [Installing Pulse](INSTALLATION.md).

## Before relying on Pulse

For any monitor that matters, do these things before treating it as operational:

1. Send a successful test from **Administration → Mail**.
2. Confirm that **Administration → Cron** shows a recent successful cron run.
3. Add the configured Pulse sender address or domain to safe-sender/allowlist rules on mail systems you control.
4. Carefully mark every recipient and safety-contact address you intend Pulse to use as checked.
5. Rehearse the workflow with harmless wording and test addresses.
6. Make sure the recipients know how to independently verify important information before acting on it.

The [monitor tutorial](MONITOR_TUTORIAL.md) contains example schedules and a rehearsal procedure.

## Key terms

- **Owner** — the signed-in Pulse user whose monitors are being run.
- **Monitor** — one check-in schedule plus its reminder, escalation, message, recipient, and document configuration.
- **Contact** — a reusable person in your Contacts list.
- **Recipient** — a contact assigned to receive the final notification for one particular monitor.
- **Safety contact** — a contact who can confirm recent direct contact and thereby postpone a monitor before final recipient notification.
- **Delivery** — the snapshotted notification and portal content prepared for one recipient during an escalation.
- **Recipient portal** — the private page from which a recipient requests an access code and, after authentication, views or downloads the documents released to them.

A single contact can be a recipient on one monitor, a safety contact on another, or both.

## Dashboard and check-ins

The dashboard gives you the operational overview: monitor counts, monitor states, the next due times, the **Check in now** action, and recent activity.

**Check in now** confirms every monitor that currently participates in normal monitoring. Each of those monitors starts a fresh interval from the time of the check-in.

You do not have to wait until a monitor is due. Checking in early simply proves that you are present now and restarts its interval.

### Optional check-in locations

Under **Monitor Editor → Schedule**, **Record location during check-ins** enables one-shot location collection for that monitor. When you enable it, Pulse asks the current device for browser location permission. The browser may remember the permission for the Pulse site; its own site settings remain the final authority. If permission is denied, revoked, times out, or no position is available, Pulse completes the check-in without a location.

Pulse asks for one current position only while enabling the option or submitting a location-aware check-in. It does not watch or record movement between check-ins. When several active monitors are checked in together, Pulse requests the position once and stores it only for monitors that have location recording enabled.

If an approximate address can be resolved, the check-in history reads that the named monitor was checked in from that location. The location is a link to OpenStreetMap. Coordinates and reported accuracy remain the authoritative stored values when no useful address is available.

**Share the last known check-in locations in the recipient portal** is a separate opt-in. Choose between 1 and 20 points. If escalation eventually releases recipient portals, Pulse copies the selected most recent points into that release in chronological order. The copy is immutable: later check-ins are never added to an already released portal. Recipients must still authenticate with their emailed access code.

The authenticated portal shows the released points after the documents in a compact **Location / Accuracy / Timestamp** table. Each location links to that individual point in OpenStreetMap. **Show locations on map** expands an interactive map directly below the table with numbered points, reported accuracy areas, and a straight chronological line between check-ins. The map supports mouse/touch panning, wheel and button zooming, keyboard navigation, **Fit**, and selectable point details. **Hide map** collapses it again.

The portal initially does not contact a map-tile service. OpenStreetMap tiles are loaded only after the recipient deliberately reveals the interactive map. OpenStreetMap then receives the visible tile area, the Pulse site origin, and normal connection information; the token-bearing portal URL and the exact Pulse point records are not sent with tile requests.

The table shows separate check-in points rather than a continuously tracked route. GPS positions can be inaccurate, and Pulse remains an additional safety tool rather than an emergency or rescue service.

Paused, escalated, and archived monitors are not included in a global check-in.

## Monitor statuses

Pulse uses these user-facing states:

- **Checked in** — the monitor is active and its next due time is still in the future.
- **Awaiting check-in** — the due time has arrived and Pulse is waiting for you.
- **Awaiting safety contact** — the owner reminder phase ended and the optional safety-contact gate is in progress.
- **Overdue** — all required waiting/reminder stages have completed and final recipient notification is ready or being attempted.
- **Escalated** — at least one final recipient notification has been accepted for delivery by the mail server. The monitoring cycle is finished and now requires an explicit lifecycle decision.
- **Paused** — the monitor is temporarily inactive.
- **Archived** — the monitor is retained as a read-only record and no longer participates in monitoring.

Pulse does not treat the passage of time alone as proof that a notification stage succeeded. Important lifecycle stages advance only after their required mail work has been processed successfully.

## Contacts

Open **Contacts** to maintain reusable people.

A contact can contain:

- name
- up to four email addresses
- **Pulse interface language**
- an optional cell-phone number
- optional notes

The cell-phone field is currently reference information only; Pulse 1.2 sends notifications by email.

### Checking an email address

Each address has its own **I checked this email address carefully** option. You may save a contact with unchecked addresses, but Pulse sends mail only to the addresses you marked as checked. The checkbox does not send a verification message and does not prove that the mailbox exists or belongs to that person. It records only that you reviewed that specific address yourself.

Pulse supports up to four separate addresses per contact. Every checked address receives its own independently queued copy, so a temporary problem at one mailbox or provider does not prevent attempts to the others. A recipient or safety contact with no checked address is clearly marked and cannot be used for delivery.

If Pulse recognizes a likely typo in a common email domain, it may display a suggestion. You remain responsible for deciding whether the address is correct.

### Editing and deleting contacts

Select a contact's **name** to edit it. The compact `⋮` menu contains destructive row actions such as Delete.

A contact cannot be deleted while any monitor still uses it as a recipient or safety contact, including an archived monitor. Remove or reassign those roles first. This prevents deleting a reusable contact from silently rewriting monitor configuration or archived records.

## Creating a monitor

Choose **Add monitor** from the Monitors page. The creation form asks for the name, optional description, owner reminder timing, and optionally some initial recipients.

A newly created monitor starts its first active cycle immediately. If you expect to spend a long time preparing a consequential monitor, create it and then use **Pause** while you finish the configuration.

After creation, Pulse opens the full Monitor Editor.

## The Monitor Editor

The Monitor Editor has **seven top-level tabs**, in this order:

1. **Details**
2. **Schedule**
3. **Documents**
4. **Recipients**
5. **Safety & escalation**
6. **Messages & content**
7. **Review & activation**

Warnings appear directly on relevant tabs when configuration needs attention.

Archived monitors can still be opened and inspected, but their monitor configuration is read-only. Use **Reset and reactivate** before changing an archived monitor.

### 1. Details

Set the monitor's name and optional description. The description is for your own reference in the monitor list and editor.

### 2. Schedule

The owner phase contains four settings:

- **Check-in interval** — how long after a successful check-in the next check-in becomes due.
- **Response window** — how long Pulse waits after the initial due notice before the first follow-up reminder.
- **Reminder interval** — time between later follow-up reminders.
- **Maximum follow-up reminders** — number of reminders after the initial due notice.

The initial due notice is not counted as a follow-up reminder.

For example:

```text
Due notice
  → 2 days
Reminder 1
  → 1 day
Reminder 2
  → 1 day
Escalation stage
```

With those settings, the owner reminder phase ends four days after the monitor first becomes due.

### 3. Documents

Documents belong to the monitor's library. You can:

- create editable Markdown text documents directly in Pulse;
- upload supported files;
- edit recipient-facing titles and descriptions;
- download or delete source documents.

Creating a document does **not** automatically release it to every recipient. Assignment is done for each recipient separately under that recipient's **Documents** tab.

Pulse text documents support a safe Markdown subset: headings, bold and italic text, ordered and unordered lists, links, blockquotes, horizontal rules, inline code, and fenced code blocks. Raw HTML is displayed as text rather than executed. The editor has **Edit** and **Preview** tabs; Preview renders the current unsaved source through Pulse's server-side Markdown renderer. Recipient downloads and **Download all** preserve the original Markdown source as `.md` files.

Each existing document has its own save button. Editing its title, description, or text marks that card with **Unsaved changes**, highlights the card and save button, and places a warning on the **Documents** tab so the state remains visible after switching sections. The warnings remain until the document is saved or every field is restored to its original value. The monitor-wide **Save changes** bar remains at the normal bottom of the editor and does not save these independent document forms.

The default upload policy accepts PDF, RTF, OpenDocument Text, Word `.docx`, JSON, CSV, common raster images (JPEG, PNG, GIF, WebP, and AVIF), plain text and Markdown, and common browser audio/video formats including MP3, M4A/AAC, Ogg, WAV, FLAC, MP4, WebM, QuickTime, and Ogg video. The default Pulse limit is 25 MiB. Administrators can change Pulse's own limit and MIME allowlist under **Administration → Files**; PHP and web-server upload limits may impose lower limits. An untouched pre-1.2.6 stock MIME list is expanded automatically, while deliberately customized administrator lists are preserved.

Acceptance does not guarantee playback in every browser. MP3 audio and H.264/AAC video in MP4 are the safest compatibility targets. Word, OpenDocument, and RTF files remain downloadable but are not converted or rendered by Pulse.

Uploaded files are stored outside the public web directory under private storage and receive internal storage names. Pulse inspects file content using Fileinfo rather than trusting the browser-supplied filename or MIME type.

### 4. Recipients

The Recipients tab shows the contacts that would receive the final notification if this monitor escalates. It summarizes each recipient's language, whether the notification email uses the monitor default or a personal override, and the number of assigned documents.

Select a recipient's **name** to open the recipient editor. Add additional recipients from the same tab.

Configuration warnings here can identify problems such as an unchecked address or a recipient email template that does not contain the required portal URL.

Removing a recipient affects the current monitor configuration only. Historical deliveries that were already released remain independent snapshots.

### 5. Safety & escalation

Choose one of two escalation policies:

- **Direct escalation** — after the owner reminder phase finishes, Pulse proceeds toward final recipient notification.
- **Safety-contact confirmation** — Pulse first asks trusted contacts whether recent direct contact with you justifies postponing the monitor.

For a safety-contact gate, configure:

- one or more safety contacts
- **Safety response window**
- **Safety reminder interval**
- **Maximum safety reminders**
- **Confirmations required**
- **Postpone by days**

Pulse explains each timing value directly below its field. If **Confirmations required** is greater than the number of selected safety contacts that currently have at least one checked email address, the editor and Review tab show an advisory warning. You may still save an incomplete setup and return to finish it, but it cannot reach that confirmation threshold until enough eligible contacts are selected.

A safety contact can only postpone escalation. They cannot make recipient notification happen sooner, see recipient messages, or access documents.

If the required confirmation count is reached, Pulse closes the current cycle and starts a new scheduled cycle. Setting **Postpone by days** to `0` uses the monitor's normal check-in interval.

If a safety contact explicitly says they cannot confirm recent contact, the existing timetable remains unchanged. If the confirmation requirement is not reached before the safety window finishes, Pulse continues toward recipient notification.

Simply opening a safety-contact link does nothing. The contact must deliberately submit a response.

### 6. Messages & content

This tab contains four secondary sections:

- **Owner check-in email**
- **Recipient email**
- **Safety-contact email**
- **Portal page**

Each installed language has its own monitor-wide defaults where appropriate. Pulse selects the language using the recipient or safety contact's **Pulse interface language**.

The monitor editor has one shared **Save changes** action for ordinary monitor settings and **Messages & content**. If you edited more than one message subsection, Pulse saves every dirty subsection before saving the monitor itself. If you try to leave the page with unsaved message changes, the browser warns before discarding them.

#### Owner check-in email

The initial due notice and the later owner follow-up reminder each have one optional custom template per monitor. Custom owner templates are not language variants: Pulse uses them exactly as written. If both subject and body are left empty, Pulse uses the built-in fallback in your configured notification language. **Show current default template** expands that localized fallback without replacing your editor.

Owner mail bodies support optional Markdown and the shared **Edit / Preview** editor. Available placeholders include `{name}`, `{monitor}`, `{due}`, and `{url}`. The initial due notice also supports `{deadline}`, `{response_window}`, and `{max_followup_reminders}`; follow-up reminders support `{number}` and `{total}`.

`{quickcheckin}` is the recommended placeholder for the optional shortcut. It expands to Pulse's localized Markdown quick-check-in link when **Administration → Security → Enable passkey quick check-in** is enabled, and to nothing when the feature is disabled. This is also how the built-in owner templates expose the feature, so the default text now shows exactly where the quick-check-in link will appear.

`{quickurl}` remains available when you want to write your own link text. It expands to the authenticated quick-check-in URL when enabled and safely falls back to the normal `{url}` login URL when disabled. Quick check-in still requires authentication and, once authenticated, checks in all active monitors.

#### Recipient email

Pulse provides localized built-in recipient email text. You may replace it with a monitor-wide subject/body for each installed language. Mail bodies are Markdown-capable and use the same **Edit / Preview** editor as other Markdown content.

Supported placeholders are:

- `{name}` — recipient name;
- `{owner}` — owner display name;
- `{monitor}` — monitor name;
- `{url}` — that recipient's private portal URL.

A custom recipient **body must contain `{url}`**. Pulse deliberately does not append a missing link automatically. `{url}` is not allowed in the subject.

A particular recipient may override the monitor-wide recipient email in the recipient editor.

#### Safety-contact email

The initial safety-contact invitation and later safety reminders can be customized separately for each language.

They support `{name}`, `{owner}`, `{monitor}`, and `{url}`. Safety reminder text additionally supports `{number}` and `{total}`.

Leave a language-specific subject/body pair empty to use Pulse's built-in localized text. Safety-contact mail bodies are also Markdown-capable.

#### Portal page

The monitor-wide portal content is separate from the notification email. Configure:

- **Page introduction** — generic explanatory text about the page;
- **Portal expiry** — 30 days, 90 days, one year, a custom number of days, or no automatic expiry.

The generic Page introduction remains plain text and supports `{name}`, `{owner}`, and `{monitor}`. Personal messages are not monitor defaults: they are written separately in an individual recipient's **Portal** tab.

In Markdown-capable fields, ending a source line with **two spaces** forces a line break without starting a new paragraph.

### 7. Review & activation

Use this tab as the final configuration summary. It shows counts, escalation policy, next due time, and important warnings.

The tab name also reflects lifecycle controls:

- a normal active monitor can be **Paused**;
- a paused monitor can be **Resumed**;
- an escalated monitor can be **Reset and reactivated** or **Archived**;
- an archived monitor can be **Reset and reactivated**.

Newly created monitors are already active; there is no separate initial activation step.

## The recipient editor

Selecting a recipient's name opens a dedicated editor with **five tabs**:

1. **Overview**
2. **Notification email**
3. **Portal**
4. **Documents**
5. **History**

### Overview

Shows the underlying contact information, address-check status, language, whether personal notification/portal overrides exist, assigned document count, and delivery-history count.

Use **Edit contact details** when the reusable contact record itself needs to change.

Removing the recipient from the monitor is also available here while the monitor is editable.

### Notification email

Choose whether the recipient uses the monitor-wide language-specific recipient template or a personal subject/body override.

Pulse shows the effective/default template in a collapsible preview. The required `{url}` rule is validated here as well.

### Portal

Configure an optional personal portal message specifically for this recipient. This is the place for a personal goodbye or anything intended only for that person. The message supports Markdown and `{name}`, `{owner}`, and `{monitor}`; it does not need `{url}` because the recipient is already on the portal.

The personal-message box appears on the authenticated portal only when **Use a personal portal message for this recipient** is enabled and the message contains text. Disabling the option retains the saved message as a reusable draft, so enabling it again restores the text. Pulse has no monitor-wide or built-in fallback personal message.

If the recipient currently has an active released delivery, this tab also lets the owner edit that delivery's **presentation** independently of future monitor settings. The introduction remains editable; a personal message can be edited only when that delivery was released with one. These changes do not alter authorization or the underlying documents.

### Documents

Choose which current monitor documents are assigned to this recipient for future releases.

If an active delivery already exists, the same tab also lets the owner edit the released documents' display titles and descriptions. These presentation edits do not change the snapshotted document set or file/text payloads.

### History

Shows delivery records and portal status. Available portals can be revoked by the owner from here.

The activity timeline records significant recipient-side events such as:

- recipient notification sent or failed
- access code requested and sent
- successful portal authentication
- individual document downloads
- **Download all**
- owner revocation
- permanent closure by the recipient

A simple unauthenticated page load is deliberately not treated as proof that the recipient personally visited the portal because mail-security systems may automatically follow links.

### Previewing the authenticated portal

Open a recipient’s **Overview** tab and select **Preview recipient portal**. Pulse opens an owner-only preview in a new browser tab using the recipient’s currently saved language, portal text, document assignments, and the monitor’s portal-location-sharing settings.

The preview URL is not a recipient invitation. It requires the monitor owner’s active Pulse login and checks ownership again on every request. Sharing the URL with somebody else does not let them view the preview. The preview also creates no release, email, access code, portal token, recipient session, or audit event. Inline document readers and media work for realistic presentation testing, while downloads and permanent access closure remain disabled.

Select **Close preview** to close that new tab and return to the recipient editor tab that was already open.

When location sharing is enabled, **Show locations on map** inside the preview expands the same interactive map below the previewed location table. No additional URL or browser tab is opened, and the map remains inside the already owner-authenticated, recipient-scoped preview.

## Recipient releases are snapshots

When Pulse stages a final recipient release, it snapshots the information needed for that delivery. Later edits to the current monitor should not silently rewrite what was already released.

In particular:

- removing a recipient from the current monitor does not invalidate an already released portal;
- changing future document assignments does not change an existing delivery;
- deleting a source text document does not remove the snapshotted text from an existing delivery;
- uploaded file payloads needed by existing deliveries remain privately retained;
- future monitor message/template changes affect future releases, not already queued/sent mail.

The owner can still edit limited **presentation** fields for an active delivery, such as portal text and released document titles/descriptions.

## Recipient portal access

The final recipient email contains a private `{url}`. Opening that URL does not reveal document content.

The recipient can request a short-lived access code by email. Access codes:

- are valid for 30 minutes
- can be used once
- are rate-limited
- are stored by Pulse only as password hashes

After successful verification, the browser receives a session scoped to that specific delivery. Authentication for one recipient delivery does not authenticate another.

Every View, Download, and **Download all** request rechecks whether the delivery is still available. If the owner revokes the portal or its automatic expiry is reached, an already-open browser session immediately loses access on the next server request.

The authenticated portal shows the page introduction, rendered Markdown personal portal message, and a responsive document grid. It uses the browser's standard controls for audio and video, native rendering for safe raster images, and on-demand framed readers for PDF, Markdown, plain text, formatted CSV, and formatted JSON. Selecting **View directly** expands the document card without leaving the portal; large text/table previews are bounded to protect browser performance. Images and videos can likewise expand within their cards.

Every available document still offers **Download**, including formats that cannot be previewed. Word, OpenDocument, RTF, HTML, SVG, unknown, or potentially active content is never passed to an external viewer and remains download-only. Every inline asset request repeats the delivery/session authorization check, and media/PDF byte-range requests support seeking without exposing the private storage path. The owner-only recipient preview uses the same presentation and authorization model, but keeps download actions disabled.

**Download all** streams a ZIP/ZIP64 archive directly from authorized private content without first creating a second full-size archive on disk.

## Closing recipient access permanently

For a delivery configured with **no automatic expiry**, an authenticated recipient can choose **Close access permanently** after saving everything they want to keep.

Pulse shows a separate warning page, offers **Download all**, requires an acknowledgement checkbox, and requires the recipient to enter a newly generated confirmation code.

Closing access permanently invalidates that delivery's portal link, unused access codes, and recipient session. It does not delete the underlying documents from Pulse. The action cannot be undone from the recipient portal.

Portals with an automatic expiry do not show this option.

## Pause, resume, escalate, archive, and delete

### Pause and resume

Use **Pause** when a monitor temporarily should not expect check-ins. Pausing closes the current active cycle and removes its due date. The monitor is excluded from **Check in now** and from normal cron progression.

**Resume** starts a fresh schedule from the time of the resume. Time spent paused does not make the monitor immediately overdue.

### After escalation

Escalation is a terminal state for that monitoring cycle. Escalated monitors do not count toward **Need attention** and are not included in **Check in now**.

Choose **Reset and reactivate** to start a new monitoring cycle using the same monitor configuration. Existing recipient deliveries from the previous escalation retain their own portal/revocation/expiry state.

Choose **Archive** to retain the monitor without continuing normal monitoring. Archived monitors are available through the **Archived** view on the Monitors page and are read-only until reset/reactivated.

### Deleting a monitor

A monitor can be deleted only while it has no recipient delivery history. Once it has produced released recipient deliveries, Pulse preserves that history and prevents deletion; use the archive lifecycle instead.

## Activity and history

Pulse records important lifecycle events including:

- check-ins
- due-state transitions
- owner reminders
- safety-contact requests and responses
- overdue transitions
- recipient delivery
- pauses and resumes
- reset/reactivation and archiving

The dashboard shows recent activity. Use **View complete activity** for the full installation history available to the signed-in user.

Recipient-specific delivery and portal activity is shown in the recipient editor's **History** tab.

## Mail queue and failed notifications

Owner notices, safety-contact messages, access codes, and recipient messages all use Pulse's mail queue. Each queued body remains an immutable text/Markdown snapshot. At delivery time Pulse creates a `multipart/alternative` message with a readable plain-text part and a sanitized HTML part with conservative inline CSS.

Administrators can open **Administration → Mail** to:

- configure SMTP
- enable or disable automatic delivery
- set retry/worker behaviour
- send a test notification;
- inspect queued, retrying, processing, sent, and failed mail
- see queue timestamps and attempt information
- retry terminally failed jobs after fixing the cause

A failed email does not count as successfully delivered. Pulse does not advance a lifecycle stage merely because a message was supposed to have been sent.

If SMTP settings are changed, send another successful test before relying on the system.

For reliable delivery, add the configured Pulse sender address or domain to safe-sender/allowlist or whitelist rules where practical. This complements rather than replaces correct SMTP, SPF, DKIM, and DMARC configuration.

## Account security, TOTP, and passkeys

Profile is organized into **Profile data**, **Account security**, and **Change password** tabs. Open **Profile → Account security** to manage the optional authenticator app and to register or remove passkeys.

### Optional authenticator-app codes

Select **Set up authenticator app**, confirm your current Pulse password, then scan the QR code with an authenticator app or a password manager such as Apple Passwords. You can also enter the displayed setup key manually. Enter one current six-digit code to prove that setup works; Pulse does not enable TOTP before this confirmation succeeds. QR generation happens locally in the browser and does not send the setup secret to another service.

After TOTP is enabled, a successful **password** sign-in leads to a second page that accepts either the current six-digit code or one unused recovery code. The setup test proves that both sides share the secret but does not consume that code as a login, so you may safely sign out and use it while it is still current. Codes are normally valid for their 30-second time step with a small adjacent-step allowance for clock skew, but a code accepted for an actual authentication cannot be replayed. Repeated failures are rate-limited.

Pulse shows 10 recovery codes once after enrollment or regeneration. Copy them to a secure place separate from the device that generates your TOTP codes, then acknowledge the list. Each recovery code works once. Profile reports how many remain, and generating a new set immediately invalidates every old code. Regenerating codes or disabling TOTP requires the current password plus a current TOTP code or unused recovery code.

When TOTP is enabled, **Recovery readiness** summarizes the current fallback state. Pulse recommends review when no passkey is registered or only three unused recovery codes remain. Generate a fresh code set before the count reaches zero, store it separately from the authenticator, and confirm that an available passkey works on a device you can still reach. Also back up the database and its matching `.env` together: the TOTP encryption and recovery-hash key in `.env` is required after a restore.

Pulse intentionally has no email-reset or security-question bypass for the TOTP step. The supported routes are an available passkey, one unused recovery code, or a complete restore of the database with its matching `.env`. The readiness display is advisory; TOTP and passkey enrollment remain optional.

TOTP is optional. Enabling it does not change passkey behavior: a successful passkey is already a complete, phishing-resistant authentication, so Pulse does not ask for TOTP afterward. This also keeps passkey quick check-in low-friction. If you lose the authenticator, use a recovery code or an available passkey; do not wait for an urgent check-in to test your recovery plan.

### Passkeys

Passkeys can be used for normal Pulse login and for quick check-in. Registration and removal require the current password, so possession of an already authenticated browser session alone is not enough to change passkey credentials.

A passkey may be backed by Face ID, Touch ID, Windows Hello, a hardware security key, or another authenticator supported by the browser and operating system. Pulse stores the credential identifier and public verification material; it does not receive the authenticator's private key or biometric template.

For the most reliable quick-check-in setup, make sure a Pulse passkey is **available** on every device you may use to respond to a reminder. Passkeys may be synchronized automatically by a password manager such as iCloud Keychain, so one registered passkey can already work on several devices. Only add a separate passkey when a device or password manager cannot access an existing one. Give independently registered credentials recognizable names such as **iCloud Keychain**, **Work password manager**, or **YubiKey**, and test each intended device before relying on quick check-in during a real reminder.

The normal password remains available, but password sign-in includes the TOTP step when the account has enabled it. A passkey remains a complete alternative authentication method.

### Quick check-in from reminder mail

Quick check-in is the recommended low-friction way to acknowledge routine Pulse reminders. When an administrator enables **Enable passkey quick check-in**, the built-in owner reminder templates use `{quickcheckin}` to place the quick-check-in link explicitly in the template. Custom templates can use `{quickcheckin}` for the localized optional block or `{quickurl}` for a custom link. The URL contains a random, expiring, single-use pointer tied to the monitoring cycle that created the reminder. Opening the URL alone does not check anything in.

The normal flow is simply: open the reminder on a device with a Pulse passkey, activate **Quick check-in**, approve Face ID, Touch ID, Windows Hello, or the available authenticator, and Pulse performs the same global check-in as **Check in now**, confirming all active monitors at once. If the passkey is unavailable or fails, choose the password fallback; when TOTP is enabled, complete its code step before Pulse performs the check-in.

Because quick check-in is intended to remove friction, do not wait for an urgent reminder to discover that a particular device has no usable passkey. Verify passkey availability on the devices you actually carry or use, then rehearse the flow.

## Administration

Users with the **administrator** role see **Administration** in the navigation. Access is also enforced server-side.

Administration contains six tabs:

- **General** — deployment environment, default language, display timezone, trusted hosts, and debug behavior.
- **Security** — session cookies/timeouts, HSTS, login throttling, and password policy.
- **Files** — upload size and MIME allowlist.
- **Mail** — SMTP, queue/retry settings, test mail, and the installation-wide mail queue.
- **Cron** — web-cron token, endpoint example, and **Last successful cron run**.
- **Installation** — read-only boot-critical information such as `.env` status, public base URL, and database connection details.

Editable settings are saved directly to the root `.env` file. Pulse does not maintain a second database-backed configuration system.

Secrets such as the SMTP password and web-cron token are never filled back into browser forms. Leaving a configured secret field blank keeps the saved value unless you explicitly clear or regenerate it.

Configuration warnings appear at the top of Administration and on affected tabs. **Last successful cron run** is flagged if cron has never completed successfully or if more than 24 hours have passed since the last successful run. This is only a coarse warning; you remain free to choose the actual cron cadence.

## Profile and languages

Your **Profile** contains personal account settings and password management. You can store up to four email addresses, sign in with any of them, and mark each address as checked independently. The first slot is labelled **Main Email** and must contain an address; the other three slots are optional. **Main Email** identifies the mandatory canonical slot, not the only login address, and its checked-delivery state remains independent. Owner due notices, reminders, and mail tests are queued separately for every checked owner address; unchecked owner addresses receive no mail.

Two language settings are separate:

- **Website language** — controls the Pulse interface for your account.
- **Notification language** — controls Pulse-authored owner notices and test mail sent to you.

Each Contact separately has a **Pulse interface language**. Pulse uses it for that person's Pulse-owned public pages, localized built-in mail text, portal access-code email, and language-specific monitor templates.

The footer language selector changes the current website language and, for a signed-in user, persists that website-language preference. It does not change a contact's language or your notification language.

Pulse ships with English, German, French, and Italian. Installed languages are discovered from `app/Lang/*.php`.

### Adding a language

This is an advanced/source-level operation. Copy an existing language file to a new locale filename, translate the strings, and set its native display name near the top:

```php
return [
	'_language.name' => 'Italiano',

	// translated Pulse strings...
];
```

Pulse discovers the file automatically. English remains the fallback for missing translation keys.

## Health checks

`/health` is a minimal public liveness endpoint.

Signed-in users can open `/health/readiness` to check important resources such as the database and private storage.

These checks complement, but do not replace, testing SMTP and confirming that cron runs successfully.

## Rehearsing with debug mode

In a non-production environment with Debug enabled, monitor row action menus expose lifecycle test actions such as **Force due now**, manual notification steps, and the safety-window expiry helper.

These actions can send real mail. Use only harmless test wording and test addresses.

**Force due now** changes the monitor state; it does not itself create the due-notice queue job. The next normal cron run detects the due monitor, queues the notice, and processes eligible mail.

See [Choosing a monitor setup](MONITOR_TUTORIAL.md#rehearse-before-relying-on-it) for a structured rehearsal sequence.

## Current limitation: no encryption at rest

Pulse 1.2 does not encrypt stored messages or documents at the application level. A compromise of the hosting account, database, filesystem, or an unencrypted backup can therefore expose those contents.

Do not use the current release as the only storage location for passwords, cryptographic recovery keys, or similarly high-value secrets. See the [Security model](SECURITY.md) for details.
