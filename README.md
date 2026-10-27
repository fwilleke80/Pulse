## Pulse 1.5.2 — Simplified navigation

User accounts is now reached through Administration → User accounts. The duplicate main-menu entry is removed; administrator permissions are unchanged. No new database migration is required.

## Pulse 1.5.1 — Administration and editor polish

This update fixes recipient document drag-and-drop, keeps account management inside the Administration navigation, moves registration settings to Configuration → Security, and adds the user table and actions menu. Dashboard Check-in and Emergency Stop now share a responsive two-column layout and matching button design. Portal document cards animate when opened and closed, with reduced-motion support.

No new database migration is required when upgrading from 1.5.0. See the [changelog](CHANGELOG.md) for validation details, including the unconfirmed browser Back/login behavior.

## Pulse 1.5.0 — Multi-user accounts and emergency-stop recovery

Version 1.5.0 adds protected superuser and administrator account management, optional verified public registration, private per-user content, and document drag-and-drop uploads. It also fixes monitors remaining Paused after an emergency stop followed by Resume. After upgrading, sign in again and choose **Resume** on the affected monitor to start a fresh interval. The upgrade never resumes stopped monitors automatically.

Migration `011_multiuser_accounts.sql` promotes the original owner to protected superuser and preserves existing account access. Public registration is disabled by default. Read [1.5.0 release and validation notes](docs/RELEASE_1.5.0.md) before deploying.

## Pulse 1.4.7 — Escalation spacing hotfix

Version 1.4.7 fixes the missing vertical spacing below the Final release grace period card in Monitor Editor → Safety & escalation. No database migration is required.

## Pulse 1.4.6 — Portal and editor presentation

Version 1.4.6 rearranges portal document cards into title/metadata, preview and actions, expands full document content inside the original preview region with Open/Close controls, and improves monitor escalation spacing and named browser titles. It does not change monitoring or delivery state transitions and requires no new database migration.

## Pulse 1.4.5 — Emergency release controls

Version 1.4.5 adds an owner-release warning before any *new* recipient release, a configurable final grace period (`0–720` hours, default `0`) and emergency stops. The release-warning message has the highest outgoing mail queue priority. A recipient release is not staged until an owner warning has been accepted by the SMTP transport and the grace period, if configured, has elapsed. SMTP acceptance is **not** confirmation that the owner read the warning. If no owner address is checked or the warning cannot be sent, release stays blocked.

The Dashboard's red **Emergency Stop** suspends all of that owner's monitors, cancels outstanding monitor-related messages, and revokes existing recipient portals. The emailed emergency link works *without a normal login* but requires an explicit confirmation (HTTP POST) and stops only the monitor named in the email. Opening the link alone cannot stop it. Emergency links are single-use, expiring and stored as SHA-256 token hashes; a forwarded/stolen email link is sufficient to authorize the named monitor's stop. Reactivation is an explicit authenticated action and leaves previously revoked portals revoked.

**Operational limitation:** Messages accepted by mail transport and documents already accessed cannot be recalled. An email already in flight when an emergency stop is pressed may still arrive. The stop prevents new release work and cancels outstanding queued work but cannot reverse an external transport's accepted message. For meaningful warning time, choose a **nonzero** grace period.

This version adds database migration `010_release_emergency_safeguards.sql`. Upgrade using the update ZIP and back up the database beforehand; complete MySQL-backed integration testing is required before relying on these irreversible-release safeguards in production.

# Pulse

**Current release: 1.5.2**

Pulse is a private, self-hosted check-in and notification system for situations in which something could happen to you and the people who need to know might otherwise not be informed for some time. You might use it to make sure family, friends, or other trusted people are contacted if you die or become seriously ill, or as an additional safety measure while travelling alone, going on an expedition, or spending time somewhere where help may be difficult to reach.

You decide how often you expect to check in. As long as you continue to do so, nothing happens. If you stop responding, Pulse first tries to reach you and, if necessary, can involve safety contacts and eventually notify the recipients you chose in advance. Recipient notifications can lead to a private portal containing messages and documents prepared for that recipient.

Pulse is not an emergency-response service. Its timing depends on your cron schedule, mail delivery, and the availability of the server on which it runs.

## Requirements

- PHP 8.4+ with the Phar extension enabled (for ZIP backup, export, and update archives)
- PDO MySQL, Fileinfo, JSON, and OpenSSL PHP extensions
- MySQL 8+ or MariaDB 10.6+
- Apache with `mod_rewrite`, or equivalent front-controller routing on another web server
- the ability to run a scheduled job by URL or command line
- HTTPS for production use

The web server must expose only `public/`. For the built-in routing used by Pulse 1.2, deploy it at the root of its host or virtual host, for example `https://pulse.example.com/`, rather than below a URL prefix such as `/pulse/`.

## Quick installation

1. Create an empty MySQL/MariaDB database and database user.
2. Upload the complete Pulse project and point the website root at `public/`.
3. Make the project root writable enough for PHP to create `.env`, and make `storage/` writable.
4. Open `/install.php` in a browser.
5. Follow the guided installer.
6. Pulse verifies the installation and attempts to remove `public/install.php` automatically. If the server cannot remove it, delete the file manually before using Pulse.
7. Log in, send a test from **Administration → Configuration → Mail**, and configure cron under **Administration → Configuration → Cron**.

The installer creates `.env`; a normal installation does not require manual dotenv editing.

For reliable notifications, configure normal sender authentication such as SPF, DKIM, and DMARC where applicable. Also add the configured Pulse sender address or domain to safe-sender/allowlist rules on mailboxes and mail servers you control.

See [docs/INSTALLATION.md](docs/INSTALLATION.md) for the complete installation, permissions, SMTP, cron, verification, and update procedure. A complete release ZIP can be uploaded over an existing installation. The installer recognizes the initialized account and attempts to remove itself without recreating configuration or users; delete `public/install.php` manually only if automatic removal fails. Preserve the server's `.env` and private `storage/` data.

When preparing source for deployment, generate `config/version.php` before uploading:

```bash
python3 tools/write_version.py
```

## Emergency Stop and final release grace period

Pulse 1.4.5 adds an **Emergency Stop** button to the authenticated Dashboard. It suspends all monitors for the account, cancels unsent queued mail, and revokes already released recipient portal access. It cannot retract messages already accepted by SMTP or downloaded documents. Explicit **Reactivate monitoring** starts fresh check-in cycles for monitors that were active before the stop; monitors already paused remain paused.

When a monitor reaches the recipient-release stage, Pulse **first** queues a priority owner warning at every checked owner email address. Each warning contains an expiring, unguessable emergency-stop URL. Visiting the URL is read-only; pressing **Emergency Stop** on the confirmation page performs a CSRF-protected POST, without requiring a normal login. The link grants stop-only authority and never gives access to documents or account settings. If there are no checked owner addresses, or all warning deliveries fail, **recipient release is blocked** until the delivery issue is resolved.

The monitor's optional **Final release grace period** is measured in hours (0–720; 0 means no extra grace period). Its clock starts when the first owner warning is **accepted by the mail transport**, not when it is queued. New recipient notifications are staged only after all owner-warning attempts have completed, at least one was accepted and the configured grace period has elapsed. Mail transport acceptance is not proof of inbox receipt. Prefer a positive grace period for consequential content.

The recipient portal and its owner preview now display up to two document cards side by side when their container is wide enough; small layouts use one column. Expanded document readers remain full width.

**Safety testing:** Because emergency stops and SMTP delivery involve concurrency, test the full release path on a separate MySQL-backed installation with non-sensitive recipients and deliberately failing SMTP deliveries before using it for real information. In-flight mail already handed off to SMTP cannot be guaranteed cancellable.

## First steps after installation

A useful first rehearsal is deliberately small:

1. Add one or two test contacts.
2. Create a test monitor.
3. Add at least one recipient and optionally a safety contact.
4. Create a harmless text document and assign it to a recipient.
5. Review the monitor's messages and escalation settings.
6. Verify a successful SMTP test and cron run.
7. Rehearse the flow with non-sensitive test addresses before relying on Pulse for a consequential monitor.

The [User guide](docs/USER_GUIDE.md) explains the interface and lifecycle. The [Monitor tutorial](docs/MONITOR_TUTORIAL.md) gives example timings and rehearsal guidance.

## Administration

Users with the `administrator` role have access to **Administration**, organized into **Overview**, **Configuration**, and **Maintenance**. Overview is a read-only system dashboard, including the former System Information details and live database/schema, cron, and maintenance status. Configuration displays **General**, **Security**, **Files**, **Mail**, and **Cron** in a single row when space permits; only Configuration displays **Save settings**. Maintenance uses matching **Backup & Restore** and **Updates** tabs, each with its own independent actions. A **Users** page will be introduced when multi-user management is implemented. Runtime settings are written directly to the root `.env` file and warnings link to the affected configuration tab. The former dedicated System Information page has been merged into Overview.

## Account security and quick check-in

Pulse 1.2 includes passkeys as a general account authentication method. Register passkeys under **Profile → Account security** and use them on the normal login page.

Pulse 1.2.3 also adds optional authenticator-app two-factor authentication (TOTP), configured under **Profile → Account security**. After you confirm the setup with a current six-digit code, password sign-ins require a current authenticator code or one unused recovery code. Passkeys remain a complete, phishing-resistant sign-in method and do not trigger an additional TOTP prompt. Enrollment QR codes are rendered locally in the browser; the authenticator secret is not sent to a QR service.

When TOTP is enabled, Pulse 1.2.7 shows a compact recovery-readiness overview in the same tab. It highlights a missing passkey and a low recovery-code count, and reminds you to back up the database together with its matching `.env`. The overview is guidance only; TOTP and passkeys remain optional.

Administrators can enable **Passkey quick check-in** under **Administration → Configuration → Security**. This is the lowest-effort way to perform routine check-ins: open the link in an owner reminder, authenticate with the passkey available on that device, and Pulse immediately performs the same global action as **Check in now** for all active monitors. The email link itself is not authentication, and normal password login remains available as fallback.

For quick check-in to stay genuinely convenient, make sure a Pulse passkey is **available** on every phone, tablet, or computer you expect to use. Password managers such as iCloud Keychain may synchronize the same passkey across several devices automatically, so do not create duplicates unnecessarily. If a device cannot access an existing passkey, register another one under **Profile → Account security**. Test quick check-in from the devices you intend to use during setup.

Owner reminder templates normally use `{quickcheckin}`, which expands to the localized quick-check-in Markdown link when enabled and to nothing when disabled. `{quickurl}` remains available for custom link wording and falls back to the normal `{url}` login URL when quick check-in is disabled.

Passkeys require HTTPS and a stable Pulse hostname in normal deployment.

## Optional check-in locations

Each monitor can independently request a one-time browser location during check-in. Enabling the setting asks the current device for permission; the browser decides whether that grant is retained. Pulse never tracks continuously, and check-in always continues if location is denied or unavailable.

Recorded owner-history locations link to OpenStreetMap. A second, separate monitor option can publish a bounded last-known history of 1–20 points in the authenticated recipient portal after escalation. That history is frozen with the release and cannot gain later check-ins. The portal presents a compact chronological table after the documents and makes no map request until the recipient deliberately selects **Show locations on map**. The map then expands below the table with numbered points, accuracy areas, and a straight chronological path plus pan, zoom, and point details. Positions are approximate and do not replace rescue or emergency services.

## Local recovery drafts for text documents (1.4.4)

When editing or creating a Markdown text document, Pulse keeps a browser-local draft of unsaved title and Markdown changes. A status below the editor distinguishes server-saved content from locally cached unsaved edits. If you revisit the same document after a failed connection, Pulse offers **Restore draft** or **Discard draft**, without silently replacing server content. A successful server acknowledgement clears the local copy; a failed or interrupted save does not. Drafts are unencrypted in browser storage and should be avoided on shared devices. Clearing site data also clears those drafts; this is a recovery aid, not a substitute for Pulse backups.

## Inline recipient documents and media

The authenticated recipient portal presents documents inline whenever the format has a safe browser or Pulse-rendered view. Recipients can read PDF, Markdown, plain text, CSV, and JSON; view common raster images; and play supported audio or video with the browser's standard controls. Larger framed readers open on demand, private media supports seeking through authenticated byte-range requests, and every available document retains an explicit **Download** action.

Pulse does not send private files to Google, Microsoft, or another external preview service. Word, OpenDocument, RTF, HTML, SVG, unknown, and potentially active formats remain download-only. Actual audio/video playback depends on the codecs supported by the recipient's browser; MP3 and H.264/AAC in MP4 are the recommended compatibility choices.

## Backup, Restore and Updates (1.4.3)

In **Administration → Maintenance → Backup & Restore**, an administrator can download a complete snapshot containing all Pulse database tables, private uploads, and installation `.env` secrets. Encryption is **optional**. With a password, Pulse encrypts the actual snapshot in authenticated AES-256-GCM chunks inside a ZIP wrapper; it can be decrypted by Pulse, but standard ZIP utilities cannot directly browse the encrypted inner contents. Without encryption, the ZIP can be inspected with ordinary ZIP programs and **contains private secrets in plaintext**. Safeguard the archive.

**Export text documents** creates a separate ZIP of the signed-in user's current Markdown (`.md`) documents, grouped by monitor, plus a JSON manifest. This does not include already released immutable snapshots and cannot replace a full backup.

**Restore backup** requires the current administrator password, a replacement acknowledgement checkbox, and final Yes/No confirmation after preflight. For safety, the full backup must come from the **same installed Pulse version**. Pulse verifies every member and SHA-256 hash, stages private uploads, writes an emergency snapshot to `storage/rollbacks/`, replaces database contents, and restores installation settings while preserving destination database connectivity, hostname, trusted hosts, and session-cookie name. All previously authenticated sessions are invalidated. After signing back in using credentials from the backup, review overdue monitors and pending mail before manually resuming monitoring from Administration. Previously delivered email is not undone.

In **Administration → Maintenance → Updates**, an administrator can upload a newer Pulse release ZIP, review preflight results and release notes without a second file selector, then authorize installation with the current administrator password (no redundant JavaScript confirmation). Updates install any pending SQL migrations. A pre-update database/private-file snapshot is preserved under `storage/rollbacks/`. The code update uses staged files and keeps `.env` and `storage/` intact. **The updater does not currently verify a publisher signature**: only upload update ZIPs from a trusted source. If a migration fails, maintenance remains locked and a **manual recovery from the emergency snapshot may be necessary**. Keep backups off-server.

**Important:** Update archives now require exactly one root-level `manifest.json`. The earlier `docs/update-manifest.json` format is no longer accepted. To upgrade from 1.4.1, first install 1.4.2; 1.4.2 can directly install 1.4.3. The bundled manifest builder produces root-only archives. The complete MySQL restore and updater have not yet been tested against a live production-scale database. Test both on a disposable installation first; do not rely on them as your only recovery method until verified in your environment.

## Documentation

- [Installation and updates](docs/INSTALLATION.md)
- [User guide](docs/USER_GUIDE.md)
- [Monitor tutorial](docs/MONITOR_TUTORIAL.md)
- [Security model](docs/SECURITY.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Release audit guide](docs/AUDIT_GUIDE.md)
- [Roadmap](docs/ROADMAP.md)
- [Changelog](CHANGELOG.md)

## License

Pulse is released under the MIT License. See [docs/LICENSE](docs/LICENSE).


For update package preflight and deterministic builds see `docs/UPDATE_PACKAGES.md`.
