# Pulse security model

Pulse is designed to keep private application data outside the public web directory, require deliberate state changes, and preserve auditable notification history. This document describes the main protections and the important limitations of Pulse 1.0.

Pulse is not an emergency-response service. Security controls do not remove the operational dependencies on the server, cron, SMTP provider, recipient mail systems, and the people involved in the monitor.

## Public and private files

Only `public/` should be reachable from the web.

The following must remain private:

- `.env`;
- application/configuration source;
- database credentials;
- logs;
- temporary files;
- uploaded documents.

The web server's document root must point to `Pulse/public/`, not to the Pulse project root.

Pulse 1.0 expects `public/` to be mounted at the root of the chosen host or virtual host. The built-in router does not support a URL-prefix deployment such as `https://example.com/pulse/`.

Uploaded documents are stored outside the public web directory. Owner downloads require authentication and monitor ownership. Recipient views/downloads require a valid released delivery and a matching authenticated recipient session.

## Secrets and `.env`

Database, SMTP, and cron credentials belong in process environment variables or the root `.env` file.

`.env` must never be:

- placed inside `public/`;
- committed to a public repository;
- included in a support archive;
- exposed through a web-server misconfiguration.

If a credential is exposed, rotate it. Deleting the leaked text does not make the old credential safe again.

## Administrator configuration

The **Administration** area is restricted server-side to users with the `administrator` role. Hiding the navigation item is not the security boundary; authenticated non-administrators receive HTTP 403 for administrator-only routes.

Administration uses the root `.env` file as the single persistent configuration source. Pulse does not copy application settings into a parallel database settings system.

The PHP account therefore needs enough access to update `.env`, while the file must remain outside `public/` and should be readable/writable by only the minimum required server accounts. Updates use a temporary file and atomic replacement to avoid leaving a truncated configuration after a partial write.

SMTP passwords and web-cron tokens are write-only values in the Administration UI. Pulse indicates whether they are configured but does not put the existing secret back into an HTML input. Process-level environment variables take precedence over `.env` and are reported as overrides.

The public base URL and database connection settings are installation-level values and remain read-only in Administration.

## Installer safety

`public/install.php` is temporary.

Normal application, web-cron, and command-line notification entry points refuse operation while the installer exists. Finalization verifies configuration, schema, and an active administrator before attempting to delete the installer.

If automatic deletion fails, Pulse remains locked until `public/install.php` is removed manually.

On an already initialized installation, the installer does not recreate users or overwrite configuration; it only recognizes the existing installation and attempts to remove itself.

## HTTPS, trusted hosts, and sessions

Production installations should use HTTPS exclusively.

Pulse uses production session-cookie protections including `HttpOnly`, `Secure`, and a configurable `SameSite` policy, together with idle and absolute session lifetime limits. Session identifiers are periodically regenerated.

Production configuration validates the trusted host list against the public base URL. HSTS can be enabled when HTTPS is permanently available.

State-changing form requests require CSRF protection. This includes signed-in owner/administrator actions and public safety-contact responses.

Repeated failed logins are throttled by account and network source without storing plaintext email addresses or IP addresses in the throttle records.

If an authenticated user's account is deleted or deactivated, later authenticated requests no longer treat the stale session as a valid user session.

## Contact addresses

Pulse's **address checked** flag means only that the signed-in owner reviewed the email address.

It does not:

- contact the person;
- prove that the mailbox exists;
- prove that the person owns the mailbox;
- record consent;
- guarantee delivery.

Safety contacts should know that they have been given that role and understand what counts as recent direct contact.

For consequential delivery, use carefully checked addresses and encourage the relevant mailboxes to allowlist the configured Pulse sender.

## Cron token and cron status

The web-cron endpoint is intentionally reachable without a normal user session because many hosting services can schedule only URLs.

It requires a random `PULSE_CRON_TOKEN` of at least 32 characters. The endpoint compares the token securely, accepts no runtime instructions from the request, starts no normal signed-in session, and returns only a minimal result.

The token is part of the URL and can appear in hosting, proxy, browser, or server logs. Treat the complete cron URL like a password and rotate the token if it is exposed.

Pulse records **Last successful cron run** only after a complete combined scheduler and mail-queue run finishes successfully. Administration warns when no successful run has ever been recorded and marks the status stale after more than 24 hours without one.

A once-per-minute cron is recommended for predictable timing. A slower schedule is possible but directly adds latency to due detection, reminders, escalation, retries, and queued mail.

## Safety-contact links

Safety-contact emails contain random purpose-specific response links. Pulse stores token hashes and validates the request state and expiry when the link is used.

Opening the link is read-only. Mail scanners, previews, and accidental link opening therefore cannot confirm that contact occurred. The safety contact must explicitly submit a form.

A safety contact can:

- confirm recent direct contact; or
- state that they cannot confirm it.

They cannot accelerate recipient release, inspect recipient messages, or access documents. A **Cannot confirm** response leaves the existing escalation timetable unchanged.

## Recipient portal authentication

Every final recipient delivery receives its own random portal token. Pulse stores the token hash in the delivery record; the raw URL necessarily exists in the outgoing notification until that mail is delivered.

Opening the portal link does not reveal document metadata or content. The recipient must authenticate with an access code sent to the configured recipient email address.

Access codes:

- are random and human-readable;
- are valid for 30 minutes;
- can be used once;
- are stored only as password hashes;
- are request/attempt rate-limited;
- invalidate older unused codes when a later code is successfully issued.

The portal does not display the configured recipient email address. Code-request responses are intentionally generic so the page does not reveal internal mail/rate-limit state.

A successful code verification creates a session entry scoped to that specific delivery. Authenticating one recipient delivery does not authenticate another.

Every authenticated portal page, inline view, individual download, and bulk download revalidates the underlying delivery. Revocation or expiry therefore takes effect on the next server request even if an old authenticated page is still open in the browser.

## Recipient portal expiry, revocation, and closure

A released portal can have an automatic expiry or no automatic expiry. The chosen policy is snapshotted into that delivery.

The authenticated owner can revoke an available delivery from the recipient's **History** tab. Revocation invalidates portal access and unused codes.

A non-expiring portal can also be permanently closed by its authenticated recipient. Closure requires:

- the authenticated delivery session;
- CSRF protection;
- an explicit acknowledgement checkbox;
- a freshly generated confirmation code.

Closure is irreversible from the recipient portal and does not delete the underlying Pulse documents.

## Uploads and documents

Pulse checks uploaded content with Fileinfo rather than trusting the browser-supplied MIME type or original filename. Files receive internal storage names and are kept outside `public/`.

Allowed file types and maximum size are configured under **Administration → Files**.

Owner downloads require normal authentication/ownership checks. Recipient document listing, previews, views, and downloads additionally require an active delivery token and the matching authenticated recipient session.

Inline rendering is restricted to passive formats such as PDF, plain text, and common raster images. Potentially active or unknown formats remain attachment-only.

## Delivery snapshots and history

Recipient releases use snapshots so later edits do not silently rewrite an issued delivery.

The release captures recipient identity/address/language, composed outgoing mail, portal availability policy, assigned document set, recipient-facing document metadata, and text-document content. Uploaded file payloads remain in private storage under immutable internal names.

Later current-monitor edits do not add/remove documents from an existing delivery. Limited presentation fields for an active delivery can still be edited deliberately by the owner, such as portal copy and released document titles/descriptions.

A monitor with recipient delivery history cannot be deleted; Archive preserves the historical boundary. A Contact still used by any monitor cannot be deleted until those current assignments are removed or changed.

## Notification integrity and queue concurrency

Pulse does not advance important monitor stages merely because a theoretical deadline passed.

For example:

- an owner notice/reminder counts only after mail delivery is accepted by SMTP;
- a safety-contact stage waits for its required notification work;
- **Escalated** is recorded only after at least one final recipient message is accepted by SMTP;
- complete final-delivery failure leaves the monitor **Overdue** instead of pretending escalation succeeded.

The mail worker uses idempotency keys, row locking, `SKIP LOCKED`, and expiring worker leases so overlapping cron workers do not normally claim the same queue item at the same time.

SMTP and the Pulse database cannot be committed atomically together. In the unusual case where an SMTP server accepts a message and the worker fails before Pulse records success, a later retry can deliver a duplicate. Consequential messages should therefore remain understandable and harmless if received more than once.

## Mail security and deliverability

Production SMTP must use STARTTLS or implicit TLS with normal certificate and hostname verification.

SMTP passwords are not written to notification logs. Queue/attempt logs avoid retaining message bodies as diagnostic log data, although a queue job necessarily needs its outgoing snapshot while delivery or retry is still pending.

After an email is accepted by the mail server, Pulse cannot recall it. The message also exists in the sender's and recipient's mail infrastructure.

Do not put passwords, cryptographic recovery keys, or other high-value secrets directly into recipient email text.

Configure SPF, DKIM, and DMARC where applicable. Add the Pulse sender address/domain to safe-sender or allowlist/whitelist rules where practical, especially for the owner mailbox. Consider doing the same for safety contacts and final recipients.

## Current limitation: no application-level encryption at rest

Pulse 1.0 does **not** encrypt stored messages or documents at the application level.

A compromise of the hosting account, database, filesystem, or an unencrypted backup can therefore expose:

- monitor-wide and recipient-specific messages;
- portal text;
- editable text documents;
- uploaded document contents.

Application-level encryption can reduce risk from database dumps, filesystem copies, and backups, but it cannot completely protect against an attacker who controls the running PHP account and can read both application memory and encryption keys. Server/hosting security remains part of the threat model.

Do not use Pulse 1.0 as the only storage location for passwords, recovery keys, or similarly high-value secrets.

## Production checklist

Before relying on a production Pulse installation:

- use a supported PHP release with security updates;
- use HTTPS exclusively;
- use Production environment and keep Debug disabled;
- keep secure cookies enabled;
- configure the exact trusted production hostname;
- point the web document root at `public/`;
- keep `.env`, storage, logs, database, and backups private;
- use strong unique database/SMTP credentials;
- keep backups of the database and uploaded documents;
- remember that messages/documents in current backups are not encrypted by Pulse;
- configure SMTP with TLS and send a successful test;
- configure SPF/DKIM/DMARC where applicable;
- allowlist/whitelist the Pulse sender where practical;
- configure cron and verify **Last successful cron run**;
- prefer a once-per-minute cron for predictable timing, or account for the delay introduced by a slower schedule;
- carefully check recipient and safety-contact addresses;
- tell safety contacts what their role means;
- rehearse consequential monitors with non-sensitive messages before relying on them;
- periodically recheck that cron, SMTP, addresses, messages, and documents are still current.
