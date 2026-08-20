# Pulse roadmap after 1.3.2

This roadmap starts from the stable Pulse 1.3.2 baseline. It is directional rather than a promise of dates; privacy, migration safety, recovery behavior, and test coverage remain release gates. The next releases remain in the 1.3.x line while smaller features accumulate; Pulse moves to 1.4 only when the combined scope justifies a larger release.

## Current baseline — Pulse 1.3.2

Pulse 1.2.0 completed the location-aware check-in and portal-preview roadmap:

- optional per-monitor one-shot geolocation during normal and quick check-ins;
- browser permission requested when location recording is enabled, with safe retries during later check-ins;
- approximate address and OpenStreetMap link in owner activity history;
- separately enabled immutable recipient snapshots of the most recent 1–20 recorded points;
- a compact post-documents location table and deliberately revealed inline map with numbered points, reported accuracy areas, and a chronological connecting line;
- an owner-only authenticated preview of the future recipient portal;
- Profile organized into **Profile data**, **Account security**, and **Change password** tabs;
- passkey registration, login, and quick-check-in reliability improvements.

Location recording and recipient sharing remain independent opt-ins. The map is neither live tracking nor proof of the route travelled.

Pulse 1.2.1 adds:

- up to four separately checked email addresses for the owner and every contact;
- independent queue delivery to every checked address, including immutable safety-contact and recipient snapshots;
- clear unchecked-address warnings without preventing a contact from being saved;
- recipient-only personal portal messages, shown only when explicitly enabled and non-empty.

Pulse 1.2.2 refines that release with:

- wider responsive email fields on Profile and contact forms;
- reusable personal portal message drafts whose enabled state controls only future releases;
- clearer monitor-specific recipient portal availability guidance;
- literal **Pulse** branding in built-in templates, without advertising the legacy `{app}` placeholder.

Pulse 1.2.3 completes optional authenticator-app two-factor authentication:

- Enrol authenticator apps using a standard TOTP secret, QR code, and manual setup key.
- Require a successful six-digit-code verification before enabling the method.
- Protect disable/reset actions with deliberate re-authentication.
- Generate one-time recovery codes, store only hashes, and support safe regeneration.
- Require TOTP after password login when the account enables it.
- Keep passkey login as a phishing-resistant complete authentication; it does not add a redundant TOTP prompt.
- Add rate limiting, replay resistance for the current time step, clock-skew bounds, audit events, localized UI, and recovery tests.
- Encrypt authenticator secrets with an installation key kept outside the database and render enrollment QR codes locally.

Pulse 1.2.4 corrects the first-login handoff so the setup-verification code is not mistaken for an already-used authentication code; no schema change is required.

Pulse 1.2.5 fixes the remaining MySQL affected-row handling error that consumed valid login codes before reporting them as invalid. Counter consumption and usage metadata now update separately inside one transaction; no schema change or TOTP re-enrollment is required.

Pulse 1.2.6 makes files and documents inline-first in the authenticated recipient portal:

- let recipients read, view, watch, or listen to every safely previewable document directly inside the portal, while retaining an explicit Download action for every available file;
- refine the portal document presentation so inline viewers, media, and remaining download-only files use layouts suited to their content;
- add default upload support for common browser-compatible audio and video formats;
- render private audio and video with the browser's standard controls, without autoplay or external player services;
- provide on-demand inline PDF viewing, expanded reading for Markdown and plain text, formatted CSV and JSON views, and native previews for additional safe raster-image formats;
- add authenticated HTTP byte-range streaming so recipients can seek through media without first downloading the complete file;
- provide the same media presentation in the owner-only recipient portal preview;
- retain explicit download actions, file metadata, immutable recipient-release authorization, and configurable upload limits.

It also marks independently edited document cards as unsaved until their own save succeeds or their values are restored, and returns the monitor-wide save bar to ordinary page flow at the bottom of the editor.

MP3 and H.264/AAC in MP4 are the primary interoperability targets. Additional accepted containers remain subject to the codecs supported by each recipient's browser.

Pulse 1.2.7 simplifies the owner interface and adds recovery-readiness guidance:

- persistent field help is visually quieter but remains available to mouse, touch, keyboard, and assistive-technology users;
- **Check in now** is a distinctive primary action, Dashboard recent activity is capped at five items, and monitor active/archive views use separate tabs;
- schedule fields explain their lifecycle effect, monitor and document editor alignment is tightened, and recipient document assignment uses compact cards;
- TOTP-enabled accounts see warnings for a missing passkey and three or fewer unused recovery codes;
- recovery guidance identifies passkey, unused recovery code, and a matching database-plus-`.env` restore as the supported routes, without adding a weaker email or security-question bypass.

Pulse 1.3.0 follows through on that interface cleanup:

- the recipient silence explanation now sits inside the related Add recipient block;
- every safety timing value has concise persistent help;
- an insufficient eligible safety-contact quorum is shown as a live advisory warning instead of preventing an incomplete configuration from being saved;
- the mandatory first owner address is identified and validated as **Main Email**, while all stored owner addresses continue to work as login aliases and each retains its independent checked-delivery state;
- browser-generated cron-token guidance appears below its action.


Pulse 1.3.1 adds operational cron diagnostics: Administration records when its persisted web-cron token actually changes and keeps a bounded administrator-visible history of unsuccessful cron calls, including the supplied invalid token when authentication fails, so stale external cron configuration can be identified without guessing.

## Later security-policy hardening

These changes should follow only after optional TOTP has been exercised in real deployments. None is scheduled for the next build yet.

- Optional administrator policy for required second-factor enrolment.
- Clear account recovery and credential-loss workflows that do not weaken the normal authentication boundary.
- Further recovery-readiness refinements only if real-world testing shows they are useful.
- Upgrade and rollback documentation for authentication-policy changes.

## Later location refinements

Candidates, not yet scheduled:

- configurable retention and deletion controls for owner check-in locations;
- optional self-hosted or administrator-selected reverse-geocoding and tile endpoints;
- additional export or incident-sharing tools only with explicit owner authorization and a narrowly defined privacy model.

## Much later

Alternative delivery channels such as SMS may be reconsidered when their operating costs, provider dependencies, privacy implications, and failure modes can be justified. Email remains the primary notification mechanism for the foreseeable future.
