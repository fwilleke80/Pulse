# Choosing a monitor setup

A Pulse monitor is not just a timer. Its check-in interval, reminder window, recipients, wording, and optional safety contacts determine what happens if you stop responding.

The examples below are starting points, not guarantees. Email can be delayed, filtered, or unavailable. Pulse is not an emergency service and should not replace emergency services, professional welfare checks, medical response plans, or legal procedures when those are appropriate.

## Understand the two escalation paths

Every active monitor begins the same way:

1. The check-in becomes due.
2. Pulse sends you a due notice.
3. The response window ends.
4. Pulse sends the configured follow-up reminders.
5. After the final reminder interval has elapsed, your own reminder phase is complete.

Then the monitor follows one of two paths.

### Direct recipient notification

Pulse proceeds directly to the final recipients.

This is the simpler option and can work well when the monitor already has a generous response period and a false notification would be manageable.

### Safety-contact confirmation

Pulse first asks one or more trusted people whether they recently had direct contact with you.

If enough safety contacts confirm, Pulse postpones the monitor and starts a fresh cycle. If the required confirmation is not reached in time, the monitor proceeds to the final recipients.

A safety contact can postpone escalation, but cannot make it happen sooner and cannot see recipient messages or documents.

## Calculate the timing

The owner phase lasts approximately:

```text
response window
+ (reminder interval × maximum follow-up reminders)
```

The initial due notice happens at the due time and does not count as a follow-up reminder.

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

A safety-contact monitor adds its safety response window and safety reminder intervals after that.

These are intended timings. Pulse advances only when the required notification stage has actually been processed; a failed mail remains visibly failed rather than being treated as delivered.

## Three practical examples

| Purpose | Check interval | Owner response | Owner reminders | Escalation | Approx. time from due to final recipient stage |
|---|---:|---:|---:|---|---:|
| Gentle routine | 14 days | 3 days | 2 × every 2 days | Direct | 7 days |
| Important welfare check | 7 days | 2 days | 2 × every 1 day | 1 safety contact | 7 days |
| High-consequence plan | 30 days | 2 days | 2 × every 1 day | 2 of 3 safety contacts | 7 days |

The final timing assumes the cron job is running and mail delivery is accepted promptly.

### Example 1: gentle routine

Use this for a low-urgency continuity check where an accidental final message would be inconvenient rather than dangerous.

Suggested settings:

- **Check-in interval:** 14 days
- **Response window:** 3 days
- **Reminder interval:** 2 days
- **Maximum follow-up reminders:** 2
- **Escalation:** Direct recipient notification

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
- **Escalation:** Safety-contact confirmation
- **Safety contacts:** 1
- **Required confirmations:** 1
- **Safety response window:** 2 days
- **Safety reminder interval:** 1 day
- **Maximum safety reminders:** 1
- **Postponement:** 7 days, or `0` to reuse the normal check-in interval

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
- **Escalation:** Safety-contact confirmation
- **Safety contacts:** 3
- **Required confirmations:** 2
- **Safety response window:** 2 days
- **Safety reminder interval:** 1 day
- **Maximum safety reminders:** 1
- **Postponement:** deliberately chosen, often the normal check-in interval

Requiring two of three confirmations protects against one mistaken confirmation. The trade-off is that the gate will continue to its deadline if only one person is available.

For genuinely time-critical danger, an email-based multi-day workflow is the wrong mechanism. Use a professional or emergency response arrangement instead of reducing every Pulse delay to its minimum.

## Build a monitor step by step

1. Create the people you need under **Contacts**.
2. Carefully check each email address and choose its notification language.
3. Create the monitor and choose its timing under **Schedule**.
4. Add the final recipients under **Recipients**.
5. Open each recipient and review the exact outgoing message.
6. Add document assignments only if useful for future document delivery; current Pulse does not send the documents.
7. Choose **Direct recipient notification** or **Safety-contact confirmation** under **Escalation**.
8. If using safety contacts, choose the contacts, required confirmation count, timing, and postponement period.
9. Review every warning under **Review & activation**.
10. Send a successful SMTP test from **Profile → Notifications**.

## Write a useful recipient message

A recipient should be able to understand the email without hidden context. Consider including:

- who configured the message
- why it has been sent
- what you want the recipient to do
- what they should verify independently before taking consequential action
- another way to verify the situation when appropriate

Avoid passwords, recovery keys, and highly sensitive secrets. The message is stored unencrypted in the current release and, when sent, is copied to the mail provider and recipient mailbox.

## Rehearse before relying on it

Use non-sensitive test contacts and wording for the first rehearsal.

1. Confirm that your cron job runs once per minute.
2. Send a test from **Profile → Notifications**.
3. Confirm that a due monitor produces the expected owner notification.
4. Rehearse a safety-contact flow if you intend to use one.
5. Confirm that opening a safety link alone changes nothing and that an explicit response is required.
6. Verify recipient delivery history on the recipient page.
7. Correct and retry one deliberately failed test message if you want to verify the failure workflow.

In a non-production environment with `PULSE_DEBUG=true`, Pulse provides lifecycle test actions such as **Force due now**, **Send due notification now**, and **Send recipient notification now**. The last action sends real recipient mail and bypasses remaining wait periods, so use only non-sensitive test recipients.

Never rehearse with wording that could alarm a real recipient.

## Review monitors when circumstances change

Revisit active monitors when relationships, email addresses, travel plans, legal arrangements, or the intended recipient actions change.

Also review the wording periodically. A message that made sense when the monitor was created may be confusing or incorrect years later.
