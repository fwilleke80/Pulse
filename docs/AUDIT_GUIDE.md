# Pulse release audit guide

This document is a repeatable pre-release audit for Pulse. It is intended for development/release testing, not normal day-to-day use.

Prefer a disposable installation containing only test contacts, test recipients, test monitors, harmless documents, and email addresses whose inboxes you control.

## 1. Prepare the test environment

Use:

- a clean webspace/document root;
- an empty test database for fresh-install testing;
- a real SMTP account that can be temporarily misconfigured;
- a working cron URL or command-line cron;
- at least two recipient inboxes;
- one safety-contact inbox;
- a private/incognito browser session for portal testing.

For debug lifecycle tests, use a non-production environment with Debug enabled. Never use alarming wording or real consequential recipients.

## 2. Fresh installation

Start with no `.env` and no Pulse tables.

Verify:

- `/install.php` opens;
- system checks distinguish blocking from non-blocking issues;
- database connection is tested before continuing;
- public base URL is suggested correctly for the host;
- timezone uses the standard timezone selector;
- default language is retained;
- the first account becomes administrator;
- SMTP can be configured or skipped;
- final verification completes;
- `public/install.php` removes itself automatically where permissions allow;
- normal Pulse starts only after the installer is gone.

After login, confirm **Administration → Cron** initially shows **Last successful cron run: Never**.

## 3. Installation verification

Verify:

- Administration opens for the administrator;
- Profile opens;
- Contacts/Monitors/Dashboard render normally;
- `/health` responds;
- `/health/readiness` works while signed in;
- SMTP test succeeds;
- web cron returns `OK` or the CLI combined runner succeeds;
- **Last successful cron run** updates.

## 4. Update/migration test

For a release after 1.0, also test updating an existing stable installation.

Verify:

- existing `.env` and private storage are preserved;
- migration checksums for old migrations remain valid;
- only new numbered migrations run;
- data survives;
- the release's included installer recognizes the existing installation and does not recreate configuration/users;
- mail and cron still work afterward.

Never edit a migration that has already shipped in a stable release.

## 5. Authentication and authorization

Verify:

- signed-out users cannot access authenticated pages;
- logout invalidates the normal session;
- stale sessions for deleted/deactivated users are rejected;
- ordinary users cannot access Administration, including by entering the URL directly;
- administrator-only POST routes also enforce the role;
- state-changing POST routes reject invalid/missing CSRF tokens.

## 6. Contacts and deletion rules

Create contacts with test addresses.

Verify:

- names are the edit links;
- address checked is only a local confirmation;
- language selection persists;
- likely email typo suggestions do not silently change the address;
- a Contact used by any monitor cannot be deleted;
- after removing all monitor roles, a Contact can be deleted.

## 7. Monitor editor structure

Verify the seven tabs:

1. Details
2. Schedule
3. Documents
4. Recipients
5. Safety & escalation
6. Messages & content
7. Review & activation

Check warning indicators and responsive behavior.

Create an archived monitor and verify its configuration is read-only with no Save bar. **Reset and reactivate** must restore editability.

Open a recipient editor and verify the owner-only portal preview:

- **Preview recipient portal** opens the saved portal presentation in a new browser tab;
- **Close preview** closes that tab rather than opening another recipient-editor tab;
- a signed-out browser and a different Pulse account cannot use the preview URL;
- the preview does not create a release, portal token, access code, recipient session, email, or audit event;
- document View/Download and permanent portal closure remain disabled;
- the preview uses the saved recipient language, portal text, document assignments, and location-sharing settings.

## 8. Check-in and owner-notice lifecycle

Create an active test monitor.

Verify:

- Check in now starts a fresh interval;
- checking in early is safe;
- paused/escalated/archived monitors are excluded from global check-in;
- location-aware monitors request one browser position, location failure does not block check-in, and monitors without the opt-in do not store the shared position;
- Force due now makes the monitor due but does not itself create the due-notice queue job;
- the next cron run creates/sends exactly one current due notice;
- repeated/manual due-notice attempts do not create a duplicate logical job.

## 9. Direct recipient escalation

For a monitor without a safety gate:

- progress through the owner reminder phase;
- trigger/stage final recipient delivery;
- verify one notification per intended recipient;
- verify the monitor becomes Escalated only after at least one final recipient notification is accepted by SMTP;
- verify Escalated does not count toward Need attention;
- verify Reset/reactivate and Archive behavior.

## 10. Safety-contact confirmation branch

Configure at least one safety contact and require one confirmation.

Verify:

- the safety email is sent once;
- merely opening/refreshing the safety page changes nothing;
- explicit confirmation postpones the monitor and sends no final recipient notification;
- the new next due time follows the configured postponement rule;
- the old safety link cannot be used again.

## 11. Safety-contact cannot-confirm branch

Use a new safety request.

Verify:

- **Cannot confirm** records the response;
- the existing timetable remains unchanged;
- the response does not accelerate final recipient release;
- the same request is not repeatedly reminded after a terminal response.

## 12. Safety timeout branch

In Debug mode, use **Expire safety contact window now**.

The debug action must only move the deadline into the past; it must not directly stage recipient delivery.

Then run normal cron.

Verify:

- production scheduler logic detects the expired gate;
- the monitor progresses to Overdue/final release normally;
- final recipients receive exactly one notification each;
- Activity reflects timeout/escalation;
- the old safety link is unavailable and retains correct language behavior.

## 13. Recipient portal pre-authentication

Open a final-recipient link in a private browser.

Before authentication verify:

- no document contents are visible;
- no document metadata/download URLs are exposed;
- the configured recipient email address is not displayed;
- requesting an access code is available.

## 14. Access-code behavior

Verify:

- one access-code request sends one code mail;
- the code works;
- a used code cannot be reused in a fresh browser session;
- an incorrect code does not authenticate;
- an immediate re-request is rate-limited without revealing sensitive state;
- a throttled re-request does not invalidate the still-valid current code.

## 15. Recipient session isolation

Authenticate Recipient A.

In another tab of the same browser session, open Recipient B's portal.

Verify Recipient B still requires their own authentication and Recipient A remains authenticated only for A's delivery.

## 16. Authenticated portal and documents

Verify:

- portal introduction/personal message are correct;
- assigned document set is correct;
- text previews are bounded and readable;
- View works for supported passive formats;
- individual Download works;
- Download all works and produces the expected ZIP contents.

For a release containing shared check-in locations, also verify:

- **Last known check-in locations** appears after the documents as a compact Location/Accuracy/Timestamp table;
- loading the authenticated portal and reading the table produces no OpenStreetMap tile request in the browser network inspector;
- **Show locations on map** expands the map below the table without opening another browser tab;
- the first tile requests occur only after that deliberate click and include no token-bearing portal path in the referrer;
- visible OpenStreetMap attribution remains present;
- numbered points are chronological, the latest point is distinguished, and selecting a point shows its label, time, and reported accuracy;
- mouse, touch, wheel, button, keyboard, and **Fit** navigation work;
- the accuracy areas and straight connecting line remain aligned while panning and zooming;
- **Hide map** collapses it, and revealing it again restores a usable map;
- the inline map remains usable on a narrow mobile viewport;
- the owner-only preview provides the same table and inline-map behavior without creating a separate map tab.

Confirm that the interface explains that the connecting line is not continuous tracking or proof of the route travelled.

## 17. Owner revocation while recipient is authenticated

Leave an authenticated portal open, then revoke the delivery from Recipient → History.

Without refreshing the old page, attempt View/Download.

Verify the request fails immediately. Then refresh/reopen the portal and confirm it remains unavailable.

Unavailable/revoked pages should be Pulse-styled and language selection should still work.

## 18. Recipient permanent closure

For a non-expiring portal:

- open **Close access permanently**;
- verify acknowledgement is required;
- verify the confirmation code is required;
- submit the correct acknowledgement/code;
- verify the delivery becomes permanently unavailable;
- verify Back/reopen does not restore access;
- verify History records recipient-controlled closure.

## 19. Delivery snapshot integrity

After a recipient release exists:

- reset/reactivate the monitor;
- remove that recipient from the current monitor;
- confirm the historical portal still works;
- delete a current source text document;
- confirm the historical delivery still contains its snapshot;
- verify current assignment changes do not alter the old delivery.

A monitor with recipient delivery history must not be deletable.

## 20. Pause/resume

Verify:

- Pause removes the due date and cron participation;
- global check-in ignores the paused monitor;
- a normal cron run does not advance it;
- Resume creates a fresh schedule from the resume time;
- historical recipient portals remain unaffected;
- Activity records Pause/Resume.

## 21. Mail failure, retry, terminal failure, and recovery

Use a test mail job, not a real recipient notification.

Temporarily configure an unreachable SMTP port with short timeout and a small Maximum attempts value.

Verify:

- the first failure becomes Retrying when attempts remain;
- the same queue row is reused;
- attempts increment;
- after the final failed attempt the job becomes **Failed — no further automatic attempts**;
- **Retry failed notifications** appears;
- after restoring SMTP, manual retry requeues the failed job;
- cron delivers it successfully;
- no stale Failed/Retrying job remains.

Restore the real settings immediately afterward.

## 22. Cron concurrency

Create one retryable/failed test job, restore SMTP, and requeue it.

Trigger two web-cron requests as close together as practical.

Verify:

- both requests may return `OK`;
- exactly one email is delivered for the queue job;
- one queue row ends Sent;
- nothing remains stuck Processing.

## 23. Mail queue presentation

Verify Administration → Mail shows useful timestamps and understandable status/details for:

- queued;
- processing;
- retrying;
- sent;
- failed.

A successful first attempt should normally show one attempt. Terminal failures must clearly indicate that automatic attempts have stopped.

## 24. Activity/history

Verify general Activity contains important monitor lifecycle events.

Recipient → History should contain delivery state plus significant portal activity such as:

- final notification sent/failed;
- access code requested/sent;
- access granted;
- document downloads;
- Download all;
- owner revocation;
- recipient closure.

Do not treat a simple unauthenticated portal GET as proof of human recipient activity because mail-security systems may follow links automatically.

## 25. Database/schema integrity

Review the current schema and new migrations for:

- explicit consistent UTF-8 collation;
- foreign keys and deletion rules;
- uniqueness constraints;
- indexes for lifecycle/queue lookups;
- orphan prevention;
- migration checksum preservation.

The stable baseline must not carry unused pre-release tables or compatibility branches.

## 26. Localization

Check English, German, French, and Italian for:

- exact key parity;
- matching placeholders;
- no raw translation keys in rendered pages;
- correct language on public safety/portal unavailable pages;
- working footer language switching;
- no obsolete version-specific UI text.

## 27. UI consistency

Compare Dashboard, Monitors, Contacts, Monitor Editor, Recipient Editor, Profile, Administration, and the public portal.

Check:

- button/link consistency;
- compact `⋮` action columns;
- empty-state actions;
- responsive tabs and tables;
- warning placement;
- destructive confirmations;
- archived read-only behavior;
- dates/timestamps;
- status terminology.

## 28. Configuration and Administration

Verify:

- `.env` remains the single persistent application-configuration source;
- secrets are not redisplayed;
- installation-level base URL/database values are read-only;
- configuration warnings point to the correct tabs;
- Time zone is a standard timezone selector;
- Last successful cron run updates only after a complete successful combined run;
- Never/Stale warning behavior is correct.

## 29. Repository/documentation cleanup

Search source and docs for:

- `TODO` / `FIXME`;
- `var_dump` / stray debug output;
- obsolete version references;
- pre-release compatibility branches;
- dead database structures;
- hard-coded deployment URLs;
- obsolete UI wording;
- documentation that no longer matches current tabs/actions.

## 30. Release package

Before shipping:

- lint every PHP file;
- parse/check JavaScript;
- run automated tests where available;
- verify translation parity/placeholders;
- verify schema/migration consistency;
- verify configuration-key coverage;
- verify version metadata;
- inspect the actual release ZIP;
- ensure no `.env`, secrets, installation-state marker, logs, or transient files are included.

## 31. Final readiness checklist

- [ ] Fresh install passes
- [ ] Stable-version update/migration passes where applicable
- [ ] Installer self-removal passes
- [ ] SMTP test passes
- [ ] Cron status updates
- [ ] Authentication/administrator authorization passes
- [ ] Check-in/owner reminder flow passes
- [ ] Direct escalation passes
- [ ] Safety confirmation/cannot-confirm/timeout branches pass
- [ ] Recipient notification passes
- [ ] Access-code security passes
- [ ] Session isolation passes
- [ ] View/Download/Download all pass
- [ ] Owner-only portal preview and inline location map pass
- [ ] Owner revocation passes
- [ ] Recipient permanent closure passes
- [ ] Delivery snapshot integrity passes
- [ ] Pause/Resume and Archive/Reset pass
- [ ] Mail retry/terminal recovery passes
- [ ] Concurrent cron claiming passes
- [ ] Destructive deletion guards pass
- [ ] Database integrity passes
- [ ] Localization passes
- [ ] Documentation matches the shipped UI
- [ ] Release ZIP passes hygiene checks
- [ ] No critical known issue remains

If all of these are satisfied, record intentionally postponed features separately rather than treating them as hidden release defects.
