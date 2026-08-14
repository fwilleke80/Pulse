# Pulse security model

Pulse is designed to keep its private application data outside the public web directory and to make notification state changes deliberate and auditable. This document describes the main protections and the important limitations of the current release.

## Public and private files

Only `public/` should be reachable from the web.

The following must remain private:

- `.env`
- application and configuration files
- database credentials
- logs
- temporary files
- uploaded documents

The web server's document root must therefore point to `Pulse/public/`, not to the Pulse project root.

Uploaded documents are stored outside the public web directory and are served only after Pulse checks the signed-in user and document ownership. Uploaded filenames are not used as public storage paths.

## Secrets

Database, SMTP, and cron credentials belong in process environment variables or the root `.env` file.

`.env` must never be:

- placed inside `public/`
- committed to a public repository
- included in a support archive
- exposed through a web-server misconfiguration

If a secret is exposed, change it. Removing a leaked value from a file does not make the old credential safe again.

## HTTPS and sessions

Production installations should use HTTPS exclusively.

Pulse uses secure session-cookie settings in production, including `HttpOnly` and `SameSite` protection, and applies idle and absolute session lifetime limits. Session identifiers are periodically regenerated.

State-changing form requests require CSRF protection. This includes normal signed-in actions and safety-contact responses.

Repeated failed logins are throttled by account and network source without storing plaintext email addresses or IP addresses in the throttle records.

## Contact addresses

Pulse's **address checked** flag means only that the signed-in owner reviewed the email address.

It does not:

- contact the person
- prove that the mailbox exists
- prove that the person owns the mailbox
- record consent
- guarantee delivery

Safety contacts should know that they have been given that role and understand what counts as recent direct contact.

## Cron token

The web cron endpoint is intentionally public because many hosting services can schedule only URLs.

It requires `PULSE_CRON_TOKEN`, which must be a random value of at least 32 characters. The endpoint compares the token securely, starts no normal user session, accepts no run settings from the request, and returns only a minimal result.

The token is part of the cron URL, so it may appear in server, proxy, or hosting logs. Treat the complete cron URL like a password and rotate the token if it is exposed.

## Safety-contact links

Safety-contact emails contain random, purpose-specific response links. The raw token is not stored as a reusable plaintext database credential; Pulse stores a hash for lookup and applies expiry and request-state checks.

Opening the link is read-only. Mail scanners, previews, and accidental link opening therefore cannot confirm that contact occurred. The safety contact must explicitly submit a form to record a response.

A safety contact can confirm recent contact or state that they cannot confirm it. They cannot use the page to accelerate recipient release, inspect recipient messages, or access documents.

## Uploads and documents

Pulse checks uploaded content using Fileinfo rather than trusting the browser-supplied MIME type or original filename. Files are renamed internally and stored outside the public web root.

Allowed file types and maximum size are configurable in `.env`.

Downloads use conservative browser headers and are delivered only after authentication and ownership checks.

## Notification integrity

Pulse does not advance important monitor stages merely because a theoretical time has passed.

For example:

- an owner reminder counts only after mail delivery is accepted by SMTP
- a safety-contact stage waits for the required notification work
- **Escalated** is recorded only after at least one final recipient message is accepted by SMTP
- a complete final-delivery failure leaves the monitor **Overdue** rather than falsely claiming escalation

Recipient releases use snapshots of the recipient identity, address, language, subject, body, and portal-availability policy. Editing a contact or message afterward cannot silently rewrite a message that has already been staged.

The mail worker uses idempotency keys, row locking, and expiring leases so overlapping cron runs do not intentionally deliver the same queue item at the same time.

SMTP itself cannot be made fully transactional with the database. In the unusual case where an SMTP server accepts a message and the database connection fails before Pulse can record that success, a later retry can produce a duplicate. Recipient messages should therefore remain understandable and harmless if received twice.

## Mail security

Production SMTP must use STARTTLS or implicit TLS with normal certificate and hostname verification.

SMTP passwords are not written to notification logs. Mail-queue and attempt logs avoid storing message body content, although the queue must retain the immutable outgoing body while a message may still need to be delivered or retried.

Once an email is accepted by the mail server, Pulse cannot recall it. The content is also exposed to the sender's mail provider and the recipient's mailbox provider.

Do not put passwords, cryptographic recovery keys, or other high-value secrets directly into recipient email text.

## Current limitations

The current release does **not** yet provide encryption for stored messages or documents.

This means a compromise of the hosting account, database, filesystem, or an unencrypted backup can expose:

- default and recipient-specific messages
- editable text documents
- uploaded document contents

Pulse 0.8.2 provides a recipient-portal invitation, authentication layer, and authenticated document delivery. Final recipient emails can contain a long-lived, recipient-specific portal URL. The raw portal token is generated randomly and stored only as a SHA-256 hash in the delivery record; the queued email body necessarily contains the raw URL until delivery, after which Pulse redacts it from the queue record.

Requesting portal access creates a random one-time code valid for 30 minutes. Pulse stores only a password hash of the code and redacts the raw code from the mail queue after delivery or terminal cancellation. Requesting a later code invalidates earlier unused codes. Portal pages never display the configured recipient email address. Code-request responses are intentionally generic so they do not disclose mail-account details or delivery state.

Portal access can expire automatically according to the policy snapshotted for that recipient delivery, and the authenticated owner can revoke an individual delivery at any time. Expiry and revocation invalidate the portal before any authenticated page is rendered.

Document listing, inline viewing, previews, and downloads require both an active portal invitation and a matching authenticated recipient session. Recipient document assignments, titles/descriptions, and text-document content are snapshotted when the release is staged so later monitor edits cannot change an issued delivery. File payloads remain in private non-public storage and are streamed only after authorization. Inline rendering is restricted to a passive MIME allowlist; potentially active formats such as SVG or HTML remain download-only.

**Download all** streams a store-only ZIP/ZIP64 archive directly from authorized private files. It does not create a second complete temporary copy and does not load the delivery into PHP memory. Pulse still does not provide application-level encryption at rest; that remains planned work for highly sensitive deployments.

Application-level encryption will reduce the risk from database dumps, filesystem copies, and backups, but it cannot completely protect against an attacker who controls the running PHP account and can read both application memory and encryption keys. Server and hosting security remain part of the overall threat model.

## Production checklist

Before relying on a production Pulse installation:

- use a supported PHP 8.4 or 8.5 release with security updates
- use HTTPS exclusively
- set production mode and disable debug output
- use secure cookies
- set the exact trusted production hostname
- point the web document root at `public/`
- restrict `.env`, storage, database, backup, and log permissions
- run `tools/write_version.py` before uploading a release
- keep database and uploaded-document backups
- remember that current backups containing messages or documents are not encrypted by Pulse
- configure SMTP with TLS and send a successful test
- run the notification cron job once per minute
- use only carefully checked recipient and safety-contact addresses
- rehearse consequential monitors with non-sensitive test messages before relying on them
