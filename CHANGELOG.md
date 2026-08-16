## 1.2.2 - 2026-08-16

### Form and portal-message refinements
- Increased the responsive minimum width of owner and contact email fields so wide screens use fewer, more comfortable columns while smaller screens continue to reflow safely.
- Preserved a recipient's personal portal message as a reusable draft when **Use a personal portal message for this recipient** is disabled. The separate enabled state now determines whether future portal releases include the saved message.
- Clarified that recipient portal availability applies to portals created by the monitor currently being edited.

### Fixed branding and editor placeholders
- Removed the `{app}` placeholder from all editor help and replaced it with the literal name **Pulse** in every built-in localized mail template. Existing custom templates containing `{app}` remain compatible.
- Added migration `005_recipient_portal_message_state.sql`, updated the reference schema, translations, documentation, and regression coverage, and advanced the release metadata to Pulse 1.2.2.

## 1.2.1 - 2026-08-16

### Redundant, separately checked email addresses
- Added four responsive email-address slots for the owner and for every contact. Each slot has its own **I checked this email address carefully** state; contacts can now be saved without checking an address, while unchecked addresses remain excluded from mail delivery.
- Added clear per-address and per-person status indicators to Contacts, monitor recipient and safety-contact lists, recipient details, and Profile. Any stored owner address can be used to sign in, while owner notifications and mail tests go only to checked addresses.
- Queue a separate message for every checked owner, safety-contact, or recipient address. Safety requests and recipient releases snapshot all checked addresses so later contact edits cannot change an active escalation; recipient access codes are likewise sent independently to every released address.
- Kept lifecycle semantics person-based: the first successful redundant delivery advances the relevant recipient or safety-contact state, while failures at one mailbox do not prevent attempts to the others.

### Recipient-only personal portal messages
- Removed the monitor-wide **Default personal portal message** from **Messages & content → Portal page**. The monitor-wide language selector, Page introduction, and portal availability remain unchanged.
- Made personal portal content exclusively recipient-specific. The authenticated portal shows its personal-message card only when **Use a personal portal message for this recipient** is enabled and a non-empty message has been saved.
- Reworded the recipient editor guidance around a personal or goodbye message and added validation that prevents an enabled-but-empty personal message.

### Database, documentation, and verification
- Added migration `004_multiple_email_addresses.sql`, including bounded owner/contact columns and immutable safety-request and recipient-delivery address snapshot tables. Existing primary owner addresses are retained as checked, and existing release rows receive their historical address snapshot.
- Updated the reference schema, user/security/architecture/tutorial/audit documentation, translations, version metadata, and source/unit regression coverage for Pulse 1.2.1.

## 1.2.0 - 2026-08-16

### Official Pulse 1.2 release
- Promoted the stabilized 1.1.6–1.1.10 work into the official 1.2 line: optional location-aware check-ins, immutable recipient location snapshots, the compact location table and on-demand inline map, tabbed Profile navigation, passkey reliability improvements, and the owner-only recipient portal preview.
- Kept location recording and recipient sharing as separate per-monitor opt-ins. Portal maps still make no OpenStreetMap tile request until deliberately revealed, while exact Pulse points and overlays remain browser-local.
- Retained the complete upload-overwrite update workflow and existing ordered migrations; no additional database migration is required for 1.2.0.

### Release documentation and audit
- Normalized current-release documentation from Pulse 1.1 to Pulse 1.2 while preserving historical changelog and migration references.
- Reframed the roadmap from the completed 1.2.0 baseline, leaving optional TOTP and recovery-policy work as later milestones rather than hidden release requirements.
- Expanded the release audit guide with owner-only portal-preview checks and explicit network, interaction, privacy, and responsive-layout checks for the inline location map.

## 1.1.10 - 2026-08-16

### Inline on-demand location map
- Moved the interactive map into **Last known check-in locations**, directly below the compact location table. **Show locations on map** expands it in place, and **Hide map** collapses it again without opening another browser tab.
- Preserved the deliberate privacy boundary: the authenticated portal initially makes no OpenStreetMap tile request. Tile loading begins only after the user reveals the map, while exact points, accuracy areas, details, and the chronological line continue to be rendered locally.
- Applied the same inline behavior to the owner-only recipient portal preview and removed the now-unneeded dedicated recipient and preview map routes.

### Scenario-based monitor tutorial
- Rewrote `docs/MONITOR_TUTORIAL.md` around two concrete uses: a cautious long-term “Am I still alive?” document-delivery monitor and a daily location-aware adventure-travel monitor.
- Added focused guidance for recipients, safety contacts, documents, timing, location permission, trail length, portal preview, rehearsals, and the limits of email-based escalation and discrete location points.
- No database migration or third-party JavaScript library is required.

## 1.1.9 - 2026-08-16

### Authenticated interactive location map
- Replaced the surrounding-area OpenStreetMap link with **View locations on map**, which opens a dedicated Pulse map page in a new tab only after deliberate recipient action.
- Added mouse, touch, wheel, button, and keyboard navigation; **Fit** returns to all recorded points. Numbered check-in nodes open their location, timestamp, reported accuracy, and individual OpenStreetMap link.
- Draws reported accuracy areas and a straight chronological line between the immutable released points. The interface clearly distinguishes that line from continuous tracking or proof of the route actually travelled.
- Added the same map to the owner-only recipient portal preview. The preview route requires the logged-in owner and a recipient lookup scoped to that owner; the real map requires the same active token-bound recipient session as the authenticated portal.

### Tile privacy and policy boundaries
- The authenticated portal table itself still makes no map request. Opening the dedicated map loads only the OpenStreetMap raster tiles intersecting the current viewport, with ordinary browser caching, visible attribution, and no background prefetch.
- Exact Pulse points, details, accuracy areas, and connecting lines are rendered locally and are not included in tile requests. OpenStreetMap receives the displayed tile area, the Pulse site origin, and normal browser connection information, but not the token-bearing portal URL.
- Updated the content-security and referrer policies for the configured tile origin while retaining `rel="noreferrer"` on direct external location links.
- No database migration or third-party JavaScript library is required.

## 1.1.8 - 2026-08-16

### Compact recipient location history
- Moved **Last known check-in locations** below the portal document section and replaced the tall numbered list and embedded map with a compact table containing **Location**, **Accuracy**, and **Timestamp** columns.
- Kept each recorded location as an explicit OpenStreetMap link and added one **Open area in OpenStreetMap** action covering all released points. Standard OpenStreetMap permalinks cannot reproduce an arbitrary Pulse point sequence as a path, so the interface no longer suggests that the surrounding-area link shows the travelled route.
- Removed automatic OpenStreetMap tile loading from recipient portals and removed the tile host from Pulse's content-security policy. Merely viewing an authenticated portal now makes no third-party map request; coordinates leave Pulse only when the recipient deliberately follows an OpenStreetMap link.

### Portal preview and update guidance
- Replaced **Back to recipient editor** in a new-tab portal preview with **Close preview**, preventing a second recipient-editor tab from being created.
- Corrected the update guide to match the verified installer behavior: a full release archive may be uploaded over an initialized installation, where `public/install.php` verifies the existing account and attempts to remove itself without recreating configuration or users.
- No database migration is required for this release.

## 1.1.7 - 2026-08-16

### Owner-only recipient portal preview
- Added **Preview recipient portal** to the recipient editor’s Overview tab. It opens the future authenticated recipient portal in a new tab using the currently saved recipient language, portal text, assigned documents, and location-sharing settings.
- Kept the preview behind the normal owner login and monitor-recipient ownership checks. Sharing its URL does not grant access, and another Pulse account cannot use the recipient ID to view it.
- Preview requests do not create releases, portal tokens, access codes, email, audit activity, or recipient sessions. Document downloads and permanent portal closure are visibly disabled.
- Current assigned text and image documents are represented in the portal layout, and current check-in locations appear only when portal location sharing is enabled for the monitor.

## 1.1.6 - 2026-08-16

### Optional location-aware check-ins
- Added a per-monitor **Record location during check-ins** option. Enabling it asks the current device for browser geolocation permission; browsers may retain that grant, while Pulse safely retries at check-in time when necessary.
- Location collection is one-shot and never continuous. A denied, revoked, timed-out, or unavailable position never blocks the check-in.
- Stored check-ins can include validated coordinates, reported accuracy, and an accuracy-appropriate approximate address resolved through OpenStreetMap Nominatim. Owner activity history links recorded locations to OpenStreetMap.
- Added a separate per-monitor opt-in to release the most recent 1–20 check-in locations to authenticated recipients. The selected chronological trail is copied into the immutable recipient release, so an existing portal cannot reveal later movements.
- Added an on-demand OpenStreetMap trail view in the authenticated recipient portal. The map loads tiles only after the recipient selects **Load map**, connects discrete check-in points with straight lines, and clearly states that it is neither continuous tracking nor an actual route.

### Profile navigation
- Reorganized Profile into the three current responsive tabs: **Profile data**, **Account security**, and **Change password**.
- Kept validation, passkey-management, and password-change redirects on their originating tabs.

### Database and verification
- Added migration `003_check_in_locations.sql`, updated the current reference schema, security policy, translations, documentation, and source/unit regression coverage.
- Added a dedicated 1.1.5-to-1.1.6 update package that excludes the fresh-install entry point, `.env`, and mutable private data; clarified that the full source archive is not a drop-in update archive.

## 1.1.5 - 2026-08-16

### Passkey login reliability
- Separated passkey sign-in controls from the native username/password form so Safari cannot accidentally submit the password form while a WebAuthn ceremony is completing.
- Added a client-side passkey-login lock that suppresses native password-form submission until passkey authentication either fails/cancels or redirects successfully.
- Changed successful passkey navigation to replace the login page in browser history.
- Added a narrow server-side safety net for an already-authenticated stale `POST /login`: the stale request remains rejected as a login attempt but is redirected to the authenticated destination instead of showing the CSRF 419 page. CSRF rotation and normal invalid-CSRF handling remain unchanged.

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
