# Monitor tutorial: two typical uses

A Pulse monitor can serve very different purposes. The same check-in and escalation machinery can quietly preserve documents for loved ones, or record a sequence of recent positions during a journey. The right settings depend on what the monitor is meant to accomplish.

This tutorial develops two practical examples:

1. **Am I still alive?** — a cautious long-term monitor whose main purpose is delivering personal messages and documents after your death.
2. **I am on an adventure vacation** — a shorter-interval monitor whose recent location-aware check-ins may help trusted people understand where you were before you stopped responding.

The values below are starting points, not guarantees. Pulse depends on its server, cron, email delivery, your device, and the people receiving its messages. It is not an emergency service, a live tracker, or a substitute for a satellite communicator, personal locator beacon, professional welfare check, medical response plan, or legal procedure.

## What every monitor does

Every active monitor follows the same basic sequence:

1. You check in and Pulse schedules the next due date.
2. If that date passes, Pulse sends the initial due notice.
3. The response window gives you time to check in.
4. Pulse sends the configured follow-up reminders.
5. Pulse either proceeds directly to the final recipients or first asks safety contacts whether they recently had direct contact with you.
6. If the situation is not resolved, Pulse creates an immutable recipient release and sends the final notifications.

The owner phase lasts approximately:

```text
response window
+ (reminder interval × maximum follow-up reminders)
```

The initial due notice occurs at the due time and is not one of the follow-up reminders. A safety-contact monitor adds its safety response window and safety reminder intervals afterward. Cron frequency and mail delivery can add further delay.

For example:

```text
Response window:       2 days
Reminder interval:     1 day
Follow-up reminders:   2

Due time               day 0
Reminder 1             day 2
Reminder 2             day 3
Owner phase complete   day 4
```

## Use case 1: “Am I still alive?”

### Purpose

This monitor is intended to remain active for months or years. If you die or become permanently unable to respond, Pulse should eventually tell selected people and give each of them the personal message and documents you prepared for them.

The priority is not speed. The priority is avoiding a false final notification while still ensuring that the material is released when you really can no longer check in.

### Suggested starting settings

- **Monitor name:** Something clear but discreet, such as `Personal continuity`.
- **Check-in interval:** 7 days.
- **Response window:** 2 days.
- **Reminder interval:** 1 day.
- **Maximum follow-up reminders:** 2.
- **Safety & escalation:** Safety-contact confirmation.
- **Safety contacts:** 3 trusted people, if that is practical.
- **Confirmations required:** 2.
- **Safety response window:** 2 days.
- **Safety reminder interval:** 1 day.
- **Maximum safety reminders:** 1.
- **Postpone by days:** 7, or `0` to reuse the normal check-in interval.
- **Record location during check-ins:** Off.
- **Share check-in locations in the recipient portal:** Off.

This example produces an approximate timeline like this:

```text
day 0   due notice
day 2   owner reminder 1
day 3   owner reminder 2
day 4   safety-contact stage begins
day 6   safety reminder
day 7   recipient stage if there was no qualifying confirmation
```

Seven days after the due date may sound slow, but a monitor of this kind should be deliberately cautious. A forgotten check-in, illness, broken phone, vacation, or mail problem should not immediately send a message that implies you may have died.

### Why use safety contacts?

A safety contact provides a human check before the final release. They should confirm only after recent direct contact with you—not because they assume you are probably fine.

Requiring two of three confirmations reduces the chance that one mistaken response postpones a real escalation. The trade-off is that the safety stage continues to its deadline if only one person is available.

A safety contact does not receive your final documents and cannot trigger an earlier escalation. Their only role is to confirm that you were recently responsive and postpone the monitor, or to say that they cannot confirm.

### Decide who receives what

Create every person under **Contacts**, then add the final recipients to the monitor. A recipient sees only the documents assigned to that recipient.

Possible material includes:

- a personal letter;
- practical information about your home, pets, possessions, or ongoing commitments;
- a list of people who should be contacted;
- funeral or memorial wishes;
- an explanation of where original legal documents are kept;
- selected photographs or other personal files.

Pulse should not be the only home of important material. Keep authoritative legal documents in the legally appropriate form and location. Avoid storing account passwords, recovery keys, or unprotected copies of extremely sensitive identity documents unless you have deliberately accepted the risk: Pulse 1.2 does not encrypt stored application data at rest.

### Write the recipient message

The notification email should be calm and understandable without hidden context. It should explain:

- that you configured Pulse yourself;
- why the message may have been sent;
- that Pulse could not verify the situation independently;
- what the recipient should check before drawing conclusions;
- what they will find in the private portal.

The email body must contain `{url}` so the recipient can reach the portal. The longer personal message belongs on the portal page, where it appears after access-code authentication.

Review the effective email, portal message, and assigned documents separately for every recipient. Different people may need very different information.

### Make weekly check-in easy

If passkey quick check-in is enabled under **Administration → Security**, the reminder email can take you directly to an authenticated check-in. Test the complete link and passkey flow on every phone or computer you may use.

A synchronized passkey may already be available on several devices. Keep password login working as a fallback.

### Rehearse this monitor

Use harmless test wording and test addresses first. Rehearse:

1. the due notice and every owner reminder;
2. the safety-contact message and explicit confirmation action;
3. progression when safety contacts do not confirm;
4. final recipient notification;
5. portal access-code authentication;
6. document viewing, individual downloads, and **Download all**;
7. portal and delivery history.

Never send realistic death-related test wording to someone who has not been warned about the rehearsal.

## Use case 2: “I am on an adventure vacation”

### Purpose

Imagine a solo bicycle trip through the Balkans. You check in regularly from your phone. Each successful check-in can record a one-time browser position. If you stop checking in and the monitor eventually escalates, the final recipients can see the most recent released positions as a chronological table and an interactive map.

Here the documents may be useful, but the primary information is the sequence of recent check-in locations.

### Suggested starting settings

Pulse schedules monitors in whole days, so its shortest check-in interval is one day.

- **Monitor name:** Something specific, such as `Balkan bicycle trip 2026`.
- **Check-in interval:** 1 day.
- **Response window:** 1 day.
- **Reminder interval:** 1 day.
- **Maximum follow-up reminders:** 1.
- **Safety & escalation:** Direct escalation, unless a particular person is genuinely able to verify you during the trip.
- **Record location during check-ins:** On.
- **Share the last known check-in locations in the recipient portal:** On.
- **Number of locations to share:** 10–20, depending on the expected trip length.

With direct escalation, the approximate timetable is:

```text
day 0   check-in becomes due and Pulse sends the due notice
day 1   owner reminder
day 2   recipient stage if you still have not checked in
```

Because the due date is already one day after the previous check-in, final recipients may not be notified until roughly three days after the last successful position. Mail or cron delays can make that longer. This is far too slow for an immediate rescue system.

For a genuinely hazardous trip, combine Pulse with an appropriate live-location or satellite safety service and an agreed emergency plan. Pulse is best treated as an additional record and notification path.

### Prepare location-aware check-in

When you enable **Record location during check-ins**, Pulse asks the current browser for location permission. The browser may remember that permission, but it can be revoked later and may be managed separately on each device.

Before departure:

1. enable location recording for the monitor;
2. allow location access on the phone and browser you will carry;
3. perform several test check-ins outdoors and indoors;
4. inspect the reported accuracy and approximate address in activity history;
5. verify that a denied or unavailable location does not block the check-in;
6. test again after browser or operating-system privacy settings change.

Pulse requests one current position per check-in. It does not run continuous background tracking. Closing the page ends the location interaction.

### Choose how many positions to release

The portal can receive the most recent 1–20 recorded positions. The selected points are copied into the recipient release at escalation time, so that portal is an immutable snapshot. It does not acquire new positions later.

For a daily check-in:

- 7 points cover roughly the last week;
- 14 points cover roughly two weeks;
- 20 points provide the maximum recent history currently available.

Choose a smaller number if recipients need only the latest area. Choose a larger number when the direction of travel matters.

### Understand the map correctly

After authenticating, a recipient sees **Last known check-in locations** below the documents. The compact table shows location, browser-reported accuracy, and timestamp.

The recipient can deliberately choose **Show locations on map**. Only then does Pulse load visible OpenStreetMap tiles. Numbered points appear in chronological order, and the most recent point is distinguished. Selecting a point shows its location label, time, accuracy, and an external OpenStreetMap link.

The connecting line is only a straight chronological connection between separate check-ins. It does not show the road or trail actually travelled, and it does not prove that you passed through any place between two points. GPS readings and approximate addresses can also be wrong or imprecise.

The owner-only recipient portal preview shows the same table and on-demand map using the monitor’s current saved check-ins. Use it before departure to confirm exactly what each recipient would see.

### Give recipients useful supporting information

For a travel monitor, consider assigning:

- a simple itinerary with intended overnight stops;
- planned dates and route alternatives;
- contact details for travel companions or local hosts;
- bicycle, vehicle, clothing, or equipment descriptions;
- insurance and assistance contact information;
- instructions about whom to contact and in what order.

Keep the information current. If plans change substantially, update the monitor’s documents and messages. Changes affect future releases, not a portal that has already been released.

### Choose recipients and safety contacts differently

Final recipients should be people who understand the trip and are prepared to interpret the location history carefully. They should know in advance that Pulse is not declaring an emergency; it is reporting that the configured check-in process reached its final stage.

A safety contact can be useful if someone has reliable daily contact with you outside Pulse. However, every safety-contact stage adds delay before the location snapshot reaches final recipients. Do not add that gate merely because it sounds safer—decide whether human verification or faster final notification is more important for this specific journey.

### Rehearse the travel flow

Before departure, run a complete harmless rehearsal:

1. perform check-ins from several different positions;
2. verify approximate addresses, timestamps, and accuracy;
3. preview the authenticated recipient portal;
4. open and operate the inline map;
5. confirm that the points are chronological and the newest point is recognizable;
6. hide and show the map again;
7. trigger a test escalation using non-alarming wording;
8. authenticate as the recipient and verify the released location snapshot;
9. confirm that cron and email are healthy;
10. check the actual phone, browser, mobile-data, and passkey setup you will carry.

Also tell the recipients what action you expect. A map without an agreed response plan can create confusion rather than help.

## Build either monitor step by step

The practical construction sequence is the same for both examples:

1. Create the people you need under **Contacts** and mark every email address you intend Pulse to use as checked.
2. Choose each contact’s **Pulse interface language**.
3. Create the monitor. A new monitor starts active immediately; pause it while preparing if necessary.
4. Under **Details**, give it a clear name and description.
5. Under **Schedule**, choose the check-in and owner-reminder timing.
6. Under **Documents**, create or upload the material that may be released.
7. Under **Recipients**, add each final recipient.
8. Open each recipient and assign only the intended documents.
9. Under **Safety & escalation**, choose direct escalation or configure the safety-contact gate.
10. Under **Messages & content**, review owner notices, safety-contact mail, recipient mail, and portal text.
11. Configure the two separate location options when the monitor needs them.
12. Resolve every warning under **Review & activation**.
13. Send a successful test from **Administration → Mail** and verify cron under **Administration → Cron**.
14. Use the recipient editor’s portal preview to inspect the saved result.

## Understand email and portal content

Pulse provides localized built-in text, but important monitors deserve deliberate wording.

Under **Messages & content → Recipient email**, custom templates can be written for every installed Pulse language. Pulse selects the version matching the recipient’s interface language. Recipient-specific overrides take precedence.

Supported recipient-email placeholders are:

- `{name}` — recipient name;
- `{owner}` — owner display name;
- `{monitor}` — monitor name;
- `{url}` — private recipient portal URL.

A custom recipient body must contain `{url}`. Pulse does not silently append a missing portal link. Do not put `{url}` in the subject.

Under **Messages & content → Portal page**:

- **Page introduction** explains the private page generally for the whole monitor;
- **Portal expiry** controls how long future released portals remain available.

Write an optional personal or goodbye message separately in each recipient's **Portal** tab. It appears only when **Use a personal portal message for this recipient** is enabled and the message contains text.

The notification email should explain why the recipient should open the private link. Longer personal text and documents belong in the access-code-protected portal.

## Test the complete infrastructure

Whichever use case you choose, verify the machinery before relying on it:

1. Confirm that cron runs at the intended cadence. Once per minute is recommended for normal operation.
2. Send a successful mail test.
3. Confirm delivery of owner notices, safety-contact messages when used, and recipient mail.
4. Test passkey and password login from the expected devices.
5. Verify portal access-code delivery and authentication.
6. Verify documents and location presentation appropriate to the monitor.
7. Inspect activity, delivery, and mail history.

### Debug lifecycle actions

With `PULSE_DEBUG=true`, the monitor `⋮` action menu exposes lifecycle test actions.

A direct monitor can progress through:

```text
Force due now
→ Send due notice now
→ Send recipient notification now
```

A safety-contact monitor can progress through:

```text
Force due now
→ Send due notice now
→ Send safety contact notification now
```

**Force due now** changes the state only; cron normally discovers the due monitor and creates its notification work. **Expire safety contact window now** moves the deadline into the past so the next cron run exercises the normal timeout path. **Send recipient notification now** is a deliberate bypass of the remaining safety timetable.

These actions can send real email. Use only harmless test contacts, recipients, wording, documents, and locations.

After a rehearsal escalates the monitor, use **Reset and reactivate** for another cycle or **Archive** to retain the completed test monitor. Archived configuration is read-only until it is reset and reactivated.

## Review monitors when life or plans change

Revisit active monitors whenever relationships, addresses, health, travel plans, intended actions, safety contacts, device permissions, or document contents change.

Historical recipient deliveries are snapshots. Editing the current monitor changes future releases, not portals that were already released.
