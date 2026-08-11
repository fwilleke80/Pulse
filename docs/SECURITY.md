# Pulse security model

## Current protection goals

Pulse 0.4.2 retains the security foundation introduced in 0.3.0 and protects against common web application failures:

- cross-user access to contacts, monitors, and documents
- cross-site request forgery
- session fixation and excessively long sessions
- external redirects
- browser content injection through inline scripts or permissive policies
- brute-force login attempts
- direct document URLs and executable upload names
- credential leakage through committed configuration
- accidental recipient-data deletion during monitor edits

The release does not claim to protect message or document confidentiality after a hosting-account, database, or filesystem compromise because encryption is not implemented yet.

## Silent contact validation

Contact address checking is deliberately owner-side. Pulse validates email syntax, offers a small set of conservative common-domain typo suggestions, and stores the time at which the signed-in owner confirmed reviewing the address.

It does not contact the address owner, request consent, verify mailbox ownership, or promise deliverability. Those actions would both disclose the future-delivery setup and conflict with the intended surprise-delivery use case.

## Secrets

Real credentials belong in process variables or the ignored `.env` file. `.env` must be readable only by the deployment account and PHP process. It must never be committed, placed below `public/`, or included in support archives.

If a credential has previously appeared in source control or an archive, removing it is not enough; rotate it.

## Public boundary

The only public directory is `public/`. The root and `storage/` `.htaccess` files deny access as a defense in depth, but correct document-root configuration remains mandatory.

Private documents are never addressed by stored filename. The application validates the user, monitor, document relationship, and a safe stored basename before streaming a download.

## Sessions and CSRF

Session cookies are `HttpOnly`, `SameSite=Strict` by default, and `Secure` in production. Pulse enforces idle, absolute, and periodic ID-regeneration limits.

Every POST request must carry the current random session token. Token validation occurs in the front controller before route dispatch, so a newly added POST route is protected by default.

## Login throttling

Pulse throttles both a normalized account subject and the direct client IP. It stores only SHA-256-derived keys, counters, and times—not the plaintext email or IP. Forwarding headers are deliberately ignored unless a later trusted-proxy design is added.

## Uploads

The upload allowlist and maximum size are controlled through `.env`. Content is identified with Fileinfo. New stored files have random `.bin` names and mode `0600`.

Allowed files are still untrusted content. Downloads therefore use attachment disposition, `nosniff`, and no-store caching headers.

Editable text documents, default messages, and recipient-specific messages are stored as unencrypted database text. Uploaded file contents are stored as unencrypted private files outside the public web root. The interface identifies this limitation where content is edited.

## Known limitations

Before Pulse is suitable for final sensitive documents, it still needs:

- authenticated document encryption at rest
- encryption of sensitive database fields
- versioned key management and documented recovery
- MFA and recent-authentication requirements for document access
- immutable audit events for security- and delivery-critical transitions
- a recipient portal using hashed, purpose-bound, expiring tokens
- staged, idempotent release and notification processing
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
- Back up the database before extracting an update; Pulse applies pending migrations automatically on the first request.
- Keep off-site backups, but do not assume unencrypted message or document backups are confidential.
- Review logs without copying sensitive document or contact data into them.
