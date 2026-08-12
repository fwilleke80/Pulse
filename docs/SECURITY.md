# Pulse security model

## Current protection goals

Pulse 0.7.1 retains the security foundation introduced in 0.3.0 and protects against common web application failures:

- cross-user access to contacts, monitors, and documents
- cross-site request forgery
- session fixation and excessively long sessions
- external redirects
- browser content injection through inline scripts or permissive policies
- brute-force login attempts
- direct document URLs and executable upload names
- credential leakage through committed configuration
- accidental recipient-data deletion during monitor edits
- partial multi-monitor check-ins and conflicting pause/check-in transitions through transactions and row locks
- false overdue states based only on theoretical elapsed time
- duplicate concurrent queue claims through row locks and expiring leases
- plaintext SMTP credentials or mail content on the wire in production through mandatory TLS or STARTTLS
- email header injection through strict queued-address and header validation
- link-scanner activation of safety decisions through read-only GET pages and explicit CSRF-protected POST responses
- long-term retention of delivered safety URLs through SHA-256 lookup hashes, expiry checks, and queue-body redaction after success or cancellation
- partial or mutable recipient staging through transactional immutable release snapshots
- false escalation before delivery through a first-recipient-SMTP-success transition

The release does not claim to protect message or document confidentiality after a hosting-account, database, or filesystem compromise because encryption is not implemented yet.

## Silent contact validation

Contact address checking is deliberately owner-side. Pulse validates email syntax, offers a small set of conservative common-domain typo suggestions, and stores the time at which the signed-in owner confirmed reviewing the address.

Address checking itself does not contact the address owner, request consent, verify mailbox ownership, or promise deliverability. An active monitor can later send the checked address a recipient notification or a safety-contact request according to its configuration. Safety contacts should know that they hold this role and understand the direct-contact standard before the monitor is relied upon.

## Secrets

Real database, SMTP, and web-cron credentials belong in process variables or the ignored `.env` file. `.env` must be readable only by the deployment account and PHP process. It must never be committed, placed below `public/`, or included in support archives.

If a credential has previously appeared in source control or an archive, removing it is not enough; rotate it.

## Public boundary

The only public directory is `public/`. The root and `storage/` `.htaccess` files deny access as a defense in depth, but correct document-root configuration remains mandatory.

Private documents are never addressed by stored filename. The application validates the user, monitor, document relationship, and a safe stored basename before streaming a download.

`public/cron/cron.php` is intentionally reachable because some hosting services can schedule only URLs. It requires an exact `PULSE_CRON_TOKEN` of at least 32 characters, compares it in constant time, starts no authenticated session, accepts no operational parameters, and returns no queue details. The token is part of the cron URL and may therefore appear in hosting or reverse-proxy access logs; treat that complete URL as a credential, restrict access to those logs, and rotate the token if it is exposed.

`/safety/confirm` is also intentionally public because the random URL token carries authority for exactly one safety request. Pulse generates 256-bit random tokens, stores SHA-256 hashes in the token lookup table, binds them to one request, and rejects expired or closed requests. The raw URL necessarily exists in the immutable queued email body while delivery or retry is pending; Pulse redacts that body after successful delivery or cancellation. A permanently failed job retains its body so an explicit retry can send the same snapshot. Invitation and reminder tokens may coexist so a reminder does not invalidate an earlier email. Tokens can also appear in recipient mailboxes, browser history, and hosting access logs; HTTPS, restrictive log access, expiry, and the configured no-referrer policy remain important.

Opening a safety URL is read-only. A scanner, mail preview, or accidental GET does not confirm or decline anything. State change requires an explicit form submission protected by the normal session-bound CSRF token. A successful request becomes unusable when its safety request is closed.

## Sessions and CSRF

Session cookies are `HttpOnly`, `SameSite=Strict` by default, and `Secure` in production. Pulse enforces idle, absolute, and periodic ID-regeneration limits.

Every POST request must carry the current random session token. Token validation occurs in the front controller before route dispatch, so a newly added POST route is protected by default.

## Login throttling

Pulse throttles both a normalized account subject and the direct client IP. It stores only SHA-256-derived keys, counters, and times—not the plaintext email or IP. Forwarding headers are deliberately ignored unless a later trusted-proxy design is added.

## Uploads

The upload allowlist and maximum size are controlled through `.env`. Content is identified with Fileinfo. New stored files have random `.bin` names and mode `0600`.

Allowed files are still untrusted content. Downloads therefore use attachment disposition, `nosniff`, and no-store caching headers.

Editable text documents, default messages, and recipient-specific messages are stored as unencrypted database text. Uploaded file contents are stored as unencrypted private files outside the public web root. The interface identifies this limitation where content is edited.

## Lifecycle integrity

All state-changing lifecycle operations lock the affected monitor rows and update cycles, monitor timing caches, and audit entries in one database transaction. A global check-in therefore confirms either every active monitor selected by that transaction or none of them.

The state machine rejects illegal or duplicate transitions. **Overdue** additionally requires the notification worker to record every configured owner reminder as sent and to wait until the full response/reminder window has elapsed. A safety-gated cycle must also deliver its invitation and configured reminders before its unanswered gate can expire.

A safety contact can move `safety_pending` only to a postponed, confirmed cycle after the configured quorum is reached. A decline cannot transition directly to overdue or escalated, so the contact cannot accelerate release. **Escalated** is recorded only when SMTP accepts the first immutable recipient message. A complete recipient-delivery failure therefore leaves the monitor at **Overdue** rather than recording a false escalation.

## Mail queue and SMTP

Production SMTP configuration requires implicit TLS or STARTTLS with peer and hostname verification. SMTP passwords are never logged. Queue and attempt logs intentionally omit body content, while the queue itself stores the immutable body needed for retries.

Owner, safety, and recipient messages use separate idempotency keys and linked state. Recipient release staging snapshots the checked address, recipient name, language, configured subject, and body inside one database transaction before queueing. Later contact or message edits do not mutate a pending or historical release. Safety requests likewise snapshot the contact identity used for that cycle.

Idempotency keys prevent the scheduler from creating the same numbered reminder twice. Workers use `FOR UPDATE SKIP LOCKED` and expiring leases, so overlapping cron runs do not deliberately process the same row. A reminder count advances only after SMTP accepts the completed message.

Recipient email is an irreversible disclosure boundary. Once SMTP accepts a message, Pulse cannot recall it from the provider or recipient mailbox. The message is also exposed to those mail systems. Do not include passwords, cryptographic recovery material, or document secrets. No 0.7.1 recipient or safety message contains document content or a document-access URL.

SMTP does not provide a fully atomic transaction with the Pulse database. If a worker loses the database connection immediately after the SMTP server accepts a message but before Pulse commits `sent`, lease recovery may retry that message and produce a duplicate. This is the standard at-least-once boundary of SMTP queues; messages should therefore remain harmless if received twice.

The current audit table records lifecycle activity but is not append-only at the database-permission level. Immutable or externally anchored audit storage remains future hardening work.

## Known limitations

Before Pulse is suitable for final sensitive documents, it still needs:

- authenticated document encryption at rest
- encryption of sensitive database fields
- versioned key management and documented recovery
- MFA and recent-authentication requirements for document access
- immutable audit events for security- and delivery-critical transitions
- a recipient document portal using hashed, purpose-bound, expiring tokens
- authorization and recent-authentication rules for that document portal
- backup and restore drills for encrypted data and keys

Application-level encryption protects database dumps, filesystem copies, and many backup leaks. It cannot fully protect against an attacker who controls the running PHP account and can read both application memory and its keys. Hosting and operating-system security therefore remain part of the threat model.

## Production checklist

- Use PHP 8.4 or 8.5 with security updates.
- Use HTTPS and keep `PULSE_COOKIE_SECURE=true`.
- Set `PULSE_ENV=production` and `PULSE_DEBUG=false`.
- Set `PULSE_TRUSTED_HOSTS` to the exact production hostname.
- Point the document root at `public/`.
- Restrict `.env`, storage, backup, and database permissions.
- Rotate credentials that existed before 0.3.0.
- Run `python3 tools/write_version.py` before uploading PHP files and include the generated `config/version.php`.
- Back up the database before extracting an update; Pulse applies pending migrations automatically on the first request.
- Keep off-site backups, but do not assume unencrypted message or document backups are confidential.
- Review logs without copying sensitive document or contact data into them.
- Send a profile test after every SMTP configuration change.
- Run either `notifications:run` or the protected web-cron URL every minute and monitor permanently failed queue jobs.
- Review every upgraded monitor's direct-or-safety policy before unpausing it.
- Use only checked recipient and safety addresses, and rehearse consequential monitors with non-sensitive test messages.
