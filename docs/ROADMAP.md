# Pulse roadmap after 1.1.5

This roadmap breaks the agreed security and check-in work into independently releasable steps. It is directional rather than a promise of dates; privacy, migration safety, recovery behavior, and test coverage remain release gates.

## Step 1 — Pulse 1.1.6: location-aware check-ins and Profile tabs

Status: implemented in 1.1.6.

- Optional per-monitor one-shot geolocation during manual, password quick, and passkey quick check-ins.
- Permission requested when location recording is enabled, with a safe retry during the next check-in when the browser requires it.
- Approximate address and OpenStreetMap link in owner history.
- Separately enabled immutable recipient-portal snapshot of the last 1–20 recorded points.
- Recipient location history refined in 1.1.8 to a compact post-documents table, in 1.1.9 with an authenticated interactive map, and in 1.1.10 with that map expanding on demand directly below the table.
- Profile organized into Profile data, Account security, and Change password tabs.

## Step 2 — Optional TOTP two-factor authentication

Planned.

- Enrol authenticator apps using a standard TOTP secret, QR code, and manual setup key.
- Require a successful six-digit-code verification before enabling the method.
- Protect disable/reset actions with deliberate re-authentication.
- Generate one-time recovery codes, store only hashes, and support safe regeneration.
- Require TOTP after password login when the account enables it.
- Keep passkey login as a phishing-resistant complete authentication by default. Requiring TOTP after a passkey would be a separate explicit strict-policy option, not an automatic consequence of enabling TOTP.
- Add rate limiting, replay resistance for the current time step, clock-skew bounds, audit events, localized UI, and recovery tests.

## Step 3 — Security-policy and recovery hardening

Planned after TOTP has been exercised in real deployments.

- Optional administrator policy for required second-factor enrolment.
- Clear account recovery and credential-loss workflows that do not weaken the normal authentication boundary.
- Security-method overview, last-used information, and user-visible recovery readiness checks.
- Upgrade and rollback documentation for authentication-policy changes.

## Later location refinements

Candidates, not yet scheduled.

- Configurable retention and deletion controls for owner check-in locations.
- Optional self-hosted or administrator-selected reverse-geocoding/tile endpoints.
- Additional export or incident-sharing tools only with explicit owner authorization and a narrowly defined privacy model.
