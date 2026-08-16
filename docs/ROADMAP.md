# Pulse roadmap after 1.2.1

This roadmap starts from the stable Pulse 1.2.1 baseline. It is directional rather than a promise of dates; privacy, migration safety, recovery behavior, and test coverage remain release gates.

## Current baseline — Pulse 1.2.1

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

## Next candidate — Optional TOTP two-factor authentication

This is the next substantial security feature under consideration, not a missing part of Pulse 1.2.

- Enrol authenticator apps using a standard TOTP secret, QR code, and manual setup key.
- Require a successful six-digit-code verification before enabling the method.
- Protect disable/reset actions with deliberate re-authentication.
- Generate one-time recovery codes, store only hashes, and support safe regeneration.
- Require TOTP after password login when the account enables it.
- Keep passkey login as a phishing-resistant complete authentication by default. Requiring TOTP after a passkey would be a separate explicit strict-policy option, not an automatic consequence of enabling TOTP.
- Add rate limiting, replay resistance for the current time step, clock-skew bounds, audit events, localized UI, and recovery tests.

## Later security-policy and recovery hardening

These changes should follow only after optional TOTP has been exercised in real deployments.

- Optional administrator policy for required second-factor enrolment.
- Clear account recovery and credential-loss workflows that do not weaken the normal authentication boundary.
- Security-method overview, last-used information, and user-visible recovery readiness checks.
- Upgrade and rollback documentation for authentication-policy changes.

## Later location refinements

Candidates, not yet scheduled:

- configurable retention and deletion controls for owner check-in locations;
- optional self-hosted or administrator-selected reverse-geocoding and tile endpoints;
- additional export or incident-sharing tools only with explicit owner authorization and a narrowly defined privacy model.

## Much later

Alternative delivery channels such as SMS may be reconsidered when their operating costs, provider dependencies, privacy implications, and failure modes can be justified. Email remains the primary notification mechanism for the foreseeable future.
