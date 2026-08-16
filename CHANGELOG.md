## 1.1.4 - 2026-08-16

### Passkey registration UX
- Replaced the browser's raw WebAuthn `InvalidStateError` during passkey registration with a friendly explanation that an existing Pulse passkey is already available on the device.
- When the account has exactly one registered passkey, the message identifies it by its Pulse label; with multiple registered passkeys the message remains generic because WebAuthn does not reveal which excluded credential matched.
- Updated passkey guidance to explain that password managers such as iCloud Keychain may synchronize one passkey across multiple devices, so users should verify availability rather than create a duplicate credential on every device.

## 1.1.3 - 2026-08-16

### Authentication UI
- Moved the normal passkey sign-in action below the username/password fields on the login page.
- After successful passkey registration, Profile now reloads immediately so the new credential appears in the passkey list without a manual refresh.

### Monitor editor save flow
- Replaced the two-step monitor/message save UX with one visible **Save changes** action. Dirty Messages & content subsections are saved first automatically, followed by the monitor settings.
- Multiple edited message subsections are saved in one pass, and leaving the page with unsaved message edits triggers the browser's unsaved-changes warning. A no-JavaScript **Save messages** fallback remains available.
- Validation failures during the combined save stay in the editor and preserve other unsaved message subsections instead of navigating away mid-save.

### Owner reminder templates
- Removed the hidden automatic quick-check-in paragraph that was appended to built-in owner reminder mail.
- Added `{quickcheckin}` as an explicit optional block placeholder in the built-in owner due/reminder templates. It expands to the localized Markdown quick-check-in link when globally enabled and to nothing when disabled.
- Kept `{quickurl}` for custom link wording; it continues to fall back to the normal `{url}` login URL when quick check-in is disabled.

### Documentation
- Expanded the passkey/quick-check-in guidance to present quick check-in as the recommended low-friction routine and to stress preparing and testing a passkey on every device that may be used to respond to reminders.

## 1.1.2 - 2026-08-16

### Passkeys and account security
- Added an extensible account-security method layer, with passkeys as the first additional authentication method and room for later second-factor methods such as TOTP or recovery codes.
- Added passkey registration/removal under Profile → Account security and passkey sign-in on the normal Pulse login page. Registration and removal require the current account password.
- WebAuthn ceremonies use fresh single-use challenges, require user verification, bind credentials to the configured Pulse RP ID/origin, store only credential public material, and verify assertions with OpenSSL.

### Quick check-in
- Added the global Administration option **Enable passkey quick check-in**. Reminder links are generated only when this option is enabled.
- Quick-check-in email links are non-authenticating, hashed server-side, expiring, single-use pointers bound to the monitoring cycle that generated them. The actual check-in requires passkey authentication or the normal password-login fallback.
- A successful quick check-in confirms **all active monitors**, matching Pulse's existing global check-in action, and records the authentication source in audit context.
- Passkey login reached through the password-fallback page also completes the pending quick check-in instead of merely signing in.

### Owner reminder mail
- Added one Markdown-capable custom template per monitor for the initial owner due notice and one for follow-up owner reminders. Built-in fallback templates remain localized to the owner notification language; custom overrides are used exactly as written.
- Added the standard **Edit / Preview** editor and collapsible **Show current default template** disclosure for both owner-mail templates.
- Added placeholders including `{url}` for the normal Pulse login and `{quickurl}` for quick check-in. When quick check-in is disabled globally, `{quickurl}` safely resolves to `{url}`.

### Markdown
- Added CommonMark-style hard line breaks: two spaces at the end of a source line now render as `<br>` in portal content and HTML email.

### Verification
- Updated the current reference schema and migration/source regression checks for the post-1.0 migration line.
- Added security UI styling and regression coverage for owner templates, quick-check-in wiring, and Markdown hard breaks.

## 1.1.0 - 2026-08-15

### Markdown content
- Added dependency-free Markdown support for Pulse-created text documents, personal portal messages, recipient notification bodies, and safety-contact mail bodies.
- Added a shared **Edit / Preview** editor. Preview renders unsaved source server-side through the same safe Markdown parser used by recipient pages.
- Supported headings, bold, italic, ordered/unordered lists, links, blockquotes, horizontal rules, inline code, and fenced code blocks. Raw HTML is escaped and unsafe link schemes are rejected.
- Recipient portal personal messages and text-document previews now render Markdown. Text documents can be opened as rendered pages while downloads preserve the original Markdown source as `.md` files, including inside **Download all** archives.

### HTML email
- SMTP delivery now emits `multipart/alternative` messages. Each queued Markdown-capable body produces a readable `text/plain` alternative and a sanitized `text/html` alternative.
- HTML mail uses conservative inline CSS and requires no external stylesheet or runtime Composer dependency.
- Existing plain-text templates remain valid Markdown source and continue to render as ordinary paragraphs, so no database migration is required.

### Verification
- Added Markdown renderer and multipart SMTP tests, localization strings, documentation updates, and release version 1.1.0.

## 1.0.0 - 2026-08-15

### Stable release
- Established Pulse 1.0.0 on the audited fresh-install database baseline.
- Added **Last successful cron run** to Administration → Cron. Successful combined web-cron and CLI `notifications:run` executions record their completion timestamp.
- Added Cron-tab configuration warnings when no successful run has ever been recorded or when the last successful run is more than 24 hours old.
- Clarified the debug **Force due now** confirmation so it explains that the due notice is queued by the next cron run rather than immediately by the button itself.
- Re-audited and substantially rebuilt the shipped 1.0 documentation against the final interface and runtime behavior: corrected the seven Monitor Editor tabs and five Recipient Editor tabs, current recipient-email `{url}` requirements, archive/delete rules, portal snapshot behavior, Administration, cron semantics, and the stable update model.
- Simplified the user-facing guides and expanded missing end-to-end workflows, including first setup, rehearsal, recipient delivery, safety contacts, history, mail recovery, and operational checks.
- Clarified the actual deployment constraint: Pulse 1.0 expects `public/` at the root of its host or virtual host; URL-prefix deployments such as `/pulse/` are not supported by the built-in router.
- Added a repeatable `docs/AUDIT_GUIDE.md` for installation, lifecycle, portal-security, queue/concurrency, integrity, localization, and release verification.

### Verification
- Final source, schema, configuration, translation, security, documentation, and package checks completed against the clean 1.0 baseline.

## 0.9.10 - 2026-08-15

### 1.0 baseline cleanup
- Squashed the complete pre-release database history into a single `001_initial_schema.sql` containing the current Pulse schema.
- Removed pre-release migration baselining and compatibility branches from the migration runner. Future schema changes will build forward from the 1.0 baseline.
- Removed obsolete migration-specific references, pre-release compatibility guidance, and now-dead delivery-retention cleanup code from the current baseline.
- Reset the current documentation to describe the clean 1.0-era installation, configuration, architecture, and update model.
- Added mail-deliverability guidance recommending safe-sender/allowlist or whitelist entries for the configured Pulse sender, alongside normal SPF/DKIM/DMARC setup.

### Data integrity
- Monitors with recipient release history can no longer be deleted. Released delivery/history is preserved; use the monitor lifecycle/archive model instead.
- Contacts that are still assigned as a recipient or safety contact on any monitor can no longer be deleted until those assignments are removed or changed.
- Added restrictive foreign-key rules to the baseline schema as a database-level backstop for current monitor/contact assignments and released monitor history.

### Interface
- Further refined Monitor Editor → Recipients so language, email-text source, and document count share the available metadata width naturally instead of leaving a trailing empty column.
- Contact and monitor action menus now reflect the new deletion guards.

### Development
- Version bumped to 0.9.10.
- This build establishes the database/source baseline intended for the final fresh-install audit before Pulse 1.0.0.
