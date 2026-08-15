# Choosing a monitor setup

A Pulse monitor is not just a timer. It watches for your continued responsiveness. Its check-in interval, reminder timing, recipients, wording, documents, and optional safety contacts determine what happens if you stop responding.

The examples below are starting points, not guarantees. Email can be delayed, filtered, or unavailable. Pulse is not an emergency service and should not replace emergency services, professional welfare checks, medical response plans, or legal procedures when those are appropriate.

## Understand the two escalation paths

Every active monitor begins the same way:

1. The check-in becomes due.
2. Pulse sends you the initial due notice.
3. The response window ends.
4. Pulse sends the configured follow-up reminders.
5. After the final reminder interval has elapsed, the owner reminder phase is complete.

Then the monitor follows one of two paths.

### Direct escalation

Pulse proceeds toward the final recipients.

This is the simpler option and can work well when the owner phase already provides enough time and a false final notification would be manageable.

### Safety-contact confirmation

Pulse first asks one or more trusted people whether they recently had direct contact with you.

If enough safety contacts confirm, Pulse postpones the monitor and starts a fresh cycle. If the required confirmation count is not reached before the safety stage ends, the monitor proceeds toward the final recipients.

A safety contact can postpone escalation, but cannot make it happen sooner and cannot see recipient messages or documents.

## Calculate the owner timing

The owner phase lasts approximately:

```text
response window
+ (reminder interval × maximum follow-up reminders)
```

The initial due notice occurs at the due time and does not count as a follow-up reminder.

Example:

```text
Response window:       2 days
Reminder interval:     1 day
Follow-up reminders:   2

Due time               day 0
Reminder 1             day 2
Reminder 2             day 3
Owner phase complete   day 4
```

A safety-contact monitor adds the safety response window and safety reminder intervals after that.

These are intended timings. Pulse advances important stages only after the required notification work has actually been processed. Cron frequency also affects precision: a once-per-minute schedule keeps delays small, while an hourly cron can add almost an hour before any eligible stage is noticed.

## Three practical examples

| Purpose | Check interval | Owner response | Owner reminders | Escalation | Approx. time from due to final recipient stage |
| --- | ---: | ---: | ---: | --- | ---: |
| Gentle routine | 14 days | 3 days | 2 × every 2 days | Direct | 7 days |
| Important welfare check | 7 days | 2 days | 2 × every 1 day | 1 safety contact | 7 days |
| High-consequence plan | 30 days | 2 days | 2 × every 1 day | 2 of 3 safety contacts | 7 days |

The final timing assumes cron is running frequently enough and mail delivery is accepted promptly.

### Example 1: gentle routine

Use this for a low-urgency continuity check where an accidental final message would be inconvenient rather than dangerous.

Suggested settings:

- **Check-in interval:** 14 days
- **Response window:** 3 days
- **Reminder interval:** 2 days
- **Maximum follow-up reminders:** 2
- **Safety & escalation:** Direct escalation

Timeline:

```text
day 0   due notice
day 3   reminder 1
day 5   reminder 2
day 7   recipient stage
```

The generous owner phase already gives you a full week after the due date, so an additional human gate may not add much value.

### Example 2: important welfare monitor

Use this when a missed check-in matters, but one trusted person is likely to know whether you are fine.

Suggested settings:

- **Check-in interval:** 7 days
- **Response window:** 2 days
- **Reminder interval:** 1 day
- **Maximum follow-up reminders:** 2
- **Safety & escalation:** Safety-contact confirmation
- **Safety contacts:** 1
- **Confirmations required:** 1
- **Safety response window:** 2 days
- **Safety reminder interval:** 1 day
- **Maximum safety reminders:** 1
- **Postpone by days:** 7, or `0` to reuse the normal check-in interval

Approximate timeline:

```text
day 0   due notice
day 2   owner reminder 1
day 3   owner reminder 2
day 4   safety-contact stage begins
day 6   safety reminder
day 7   recipient stage if there was no qualifying confirmation
```

The safety contact should confirm only after actual recent direct contact, not merely because they assume everything is fine.

### Example 3: high-consequence monitor

Use this only after rehearsing the entire workflow and discussing the role with the people involved.

Suggested settings:

- **Check-in interval:** choose a cadence that matches the real purpose; 30 days is only an example
- **Response window:** 2 days
- **Reminder interval:** 1 day
- **Maximum follow-up reminders:** 2
- **Safety & escalation:** Safety-contact confirmation
- **Safety contacts:** 3
- **Confirmations required:** 2
- **Safety response window:** 2 days
- **Safety reminder interval:** 1 day
- **Maximum safety reminders:** 1
- **Postpone by days:** deliberately chosen, often the normal check-in interval

Requiring two of three confirmations protects against one mistaken confirmation. The trade-off is that the gate continues to its deadline if only one person is available.

For genuinely time-critical danger, an email-based multi-day workflow is the wrong mechanism. Use a professional or emergency response arrangement instead of reducing every Pulse delay to its minimum.

## Build a monitor step by step

A practical setup sequence is:

1. Create the people you need under **Contacts**.
2. Carefully check each email address and choose the contact's **Pulse interface language**.
3. Create the monitor. Remember that a new monitor starts active immediately; pause it while preparing if necessary.
4. Under **Details**, give it a clear name and description.
5. Under **Schedule**, choose the owner timing.
6. Under **Documents**, create/upload anything the final recipients may need.
7. Under **Recipients**, add the final recipients.
8. Open each recipient and assign documents under the recipient's **Documents** tab.
9. Under **Safety & escalation**, choose Direct escalation or Safety-contact confirmation. If using safety contacts, configure the contacts, confirmation count, timing, and postponement period.
10. Under **Messages & content**, review Recipient email, Safety-contact email, and Portal page content.
11. Open each recipient and inspect the effective **Notification email** and any personal portal override.
12. Resolve every warning under **Review & activation**.
13. Send a successful test from **Administration → Mail** and verify cron under **Administration → Cron**.

## Write a useful recipient email

Pulse provides localized built-in recipient text, so a monitor can operate without custom wording.

Under **Messages & content → Recipient email**, monitor-wide custom templates can be written separately for every installed Pulse language. Pulse selects the version matching each recipient's **Pulse interface language**. Recipient-specific personal overrides take precedence.

Supported placeholders are:

- `{app}` — Pulse;
- `{name}` — recipient name;
- `{owner}` — owner display name;
- `{monitor}` — monitor name;
- `{url}` — private recipient portal URL.

A custom recipient **body must contain `{url}`**. Pulse does not silently append a missing portal link. Do not put `{url}` in the subject.

A recipient should be able to understand the email without hidden context. Consider including:

- who configured the message;
- why it has been sent;
- what you want the recipient to do;
- what they should verify independently before taking consequential action;
- another way to verify the situation when appropriate.

Avoid passwords, recovery keys, and highly sensitive secrets. The message is stored unencrypted in Pulse 1.0 and, once sent, is also copied to the sender's and recipient's mail systems.

## Decide what belongs in email and what belongs in the portal

The notification email should be enough for the recipient to understand why they should open the private link. The portal is better for longer personal text and documents.

Under **Messages & content → Portal page**:

- **Personal portal message** is the owner's message to the recipient;
- **Page introduction** explains the private Pulse page more generally;
- **Portal expiry** controls how long future released portals remain available.

Do not use email as the only place for sensitive details that are better kept behind the portal access-code step.

## Choose safety contacts carefully

A safety contact is not a final recipient and does not receive the final documents. Their job is narrow: determine whether they can truthfully confirm recent direct contact.

Good safety contacts should:

- know that Pulse may contact them;
- understand what counts as direct contact;
- use an email address they actually monitor;
- avoid confirming merely because they assume you are probably fine.

If a contact says **Cannot confirm**, the existing escalation timetable remains unchanged. It is intentionally not an emergency trigger.

## Rehearse before relying on it

Use non-sensitive test contacts and wording for the first rehearsal.

At minimum:

1. Confirm that cron is running at the cadence you intend to use. Once per minute is recommended for normal operation.
2. Send a test from **Administration → Mail**.
3. Confirm that a due monitor produces the expected owner notification after the next cron run.
4. Rehearse a safety-contact flow if you intend to use one.
5. Confirm that opening a safety link alone changes nothing and that an explicit response is required.
6. Verify final recipient notification.
7. Request and use a portal access code.
8. Verify View, Download, and **Download all** for released documents.
9. Verify recipient delivery/activity history.
10. If useful, deliberately fail one test mail and verify retry/recovery behavior.

### Debug lifecycle actions

In a non-production environment with `PULSE_DEBUG=true`, the monitor `⋮` action menu exposes test actions.

A direct monitor can progress through:

```text
Force due now
→ Send due notice now
→ Send recipient notification now
```

**Force due now** changes the monitor state only. The normal cron run is still responsible for discovering the due monitor and creating its due-notice queue job. If cron has already sent the due notice, a manual **Send due notice now** attempt should not create a duplicate.

A safety-contact monitor can progress through:

```text
Force due now
→ Send due notice now
→ Send safety contact notification now
```

While the safety gate is active, **Expire safety contact window now** moves only the safety deadline into the past. The next normal cron run then exercises the real production timeout path. **Send recipient notification now** remains a separate debug bypass when you deliberately want to skip the remaining safety timetable.

These actions can send real mail. Use only harmless test contacts and recipients.

After recipient delivery escalates the monitor, use **Reset and reactivate** for another rehearsal cycle or **Archive** to keep the completed test monitor for reference. Archived monitor configuration is read-only until reset/reactivated.

Never rehearse with wording that could alarm a real recipient.

## Review monitors when circumstances change

Revisit active monitors when relationships, email addresses, travel plans, legal arrangements, intended recipient actions, or document contents change.

Also review the wording periodically. A message that made sense when the monitor was created may be confusing or incorrect years later.

Remember that historical recipient deliveries are snapshots. Editing the current monitor changes future releases, not already released portals.
