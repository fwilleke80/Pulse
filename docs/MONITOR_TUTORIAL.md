# Tutorial: choosing a monitor for different levels of seriousness

Pulse monitors are timers with consequences. A more serious monitor is not simply one with the shortest delay: it is one whose recipients, wording, confirmation rules, and total timeline match the real-world situation.

This tutorial provides starting points, not guarantees. Email can be delayed, filtered, duplicated, or unavailable, and Pulse is not an emergency service. Do not use it instead of calling emergency services, arranging professional welfare checks, or following a medical or legal response plan.

## First understand the two escalation paths

Every monitor begins the same way:

1. The check interval reaches its due time.
2. Pulse sends the owner an immediate due notice.
3. The owner response window closes.
4. Pulse sends the configured number of owner follow-up reminders.
5. After the last reminder has had its complete interval, the owner phase ends.

The monitor then follows one of two policies:

- **Direct recipient notification** — Pulse marks the cycle **Overdue**, snapshots all valid recipient messages, and queues them immediately.
- **Safety-contact gate** — Pulse asks the configured safety contacts whether they recently had direct contact with the owner. Reaching the confirmation quorum postpones the monitor. Otherwise, after the safety response and reminder periods are fully delivered and elapsed, Pulse marks it **Overdue** and queues recipient messages.

A safety contact can postpone delivery or say that they cannot confirm recent contact. They cannot make delivery happen sooner, read recipient messages, or access documents.

## How the timing is calculated

Owner follow-up reminder 1 is scheduled when the owner response window ends. Later reminders are separated by the owner reminder interval.

```text
owner phase end = due time
                + response window
                + (reminder interval × maximum follow-up reminders)
```

With zero follow-up reminders, the owner phase ends when the response window closes.

For a safety-gated monitor, the safety clock begins only after every initial safety invitation was accepted by SMTP:

```text
safety gate end = invitation completion time
                + safety response window
                + (safety reminder interval × maximum safety reminders)
```

Pulse advances only after the required email stage was actually accepted by SMTP. A permanently failed owner, safety, or recipient message stays visibly failed; elapsed time alone does not pretend it was delivered.

## Three practical starting profiles

The values below are examples. Use addresses you have checked, discuss the role with safety contacts, and perform a non-sensitive rehearsal before enabling a consequential monitor.

| Level | Typical purpose | Check interval | Owner response | Owner reminders | Policy | Safety gate | Approximate time from due to recipient queue |
|---|---|---:|---:|---:|---|---|---:|
| Gentle | Routine continuity, low urgency | 14 days | 3 days | 2 every 2 days | Direct | None | 7 days |
| Important | Welfare concern where a trusted person may know you are fine | 7 days | 2 days | 2 every 1 day | Safety contact | 1 of 1; 2-day response; 1 reminder after 1 day | 7 days |
| High consequence | Carefully reviewed plan with multiple independent checks | 30 days | 2 days | 2 every 1 day | Safety contact | 2 of 3; 2-day response; 1 reminder after 1 day | 7 days |

“Approximate” assumes cron runs normally and SMTP accepts each stage promptly. Recipient mail can be queued on that schedule, but **Escalated** appears only after the first recipient email is actually accepted by SMTP.

### Level 1: gentle routine

Use this when the purpose is a calm, low-urgency continuity check and an accidental notification would be inconvenient rather than dangerous.

Suggested setup:

- **Check-in interval:** 14 days
- **Owner response window:** 3 days
- **Owner reminder interval:** 2 days
- **Maximum follow-up reminders:** 2
- **Escalation policy:** Direct recipient notification
- **Recipients:** one or two people who understand the message is automated
- **Message:** short, factual, and explicit about what the recipient should do

Example timeline:

- day 0: due notice
- day 3: owner reminder 1
- day 5: owner reminder 2
- day 7: recipient messages are staged; first successful delivery changes the monitor to **Escalated**

Why direct delivery fits: the long owner window already absorbs ordinary delay, while an additional human gate would add complexity without much benefit.

### Level 2: important welfare monitor

Use this when a missed check-in matters, but one trusted person is likely to know whether the owner is safe. The gate reduces false recipient notifications without allowing that person to trigger them.

Suggested setup:

- **Check-in interval:** 7 days
- **Owner response window:** 2 days
- **Owner reminder interval:** 1 day
- **Maximum follow-up reminders:** 2
- **Escalation policy:** Safety-contact gate
- **Safety contacts:** one checked address
- **Required confirmations:** 1
- **Safety response window:** 2 days
- **Safety reminder interval:** 1 day
- **Maximum safety reminders:** 1
- **Postpone after confirmation:** 7 days, or `0` to reuse the normal check interval

Example timeline:

- day 0: due notice
- day 2: owner reminder 1
- day 3: owner reminder 2
- day 4: safety invitation after the owner phase ends
- day 6: safety reminder
- day 7: if no qualifying confirmation exists, recipient messages are staged

The safety contact should confirm only after recent direct contact, not merely because they assume everything is fine. Opening the link records nothing. A checked confirmation explicitly closes the current cycle and starts a fresh scheduled cycle for the configured postponement duration.

### Level 3: high-consequence monitor

Use this only after rehearsing the full workflow and reviewing the consequences with the people involved. The goal is defense in depth, not the shortest timer.

Suggested setup:

- **Check-in interval:** choose the real operational cadence; 30 days is only an example
- **Owner response window:** 2 days
- **Owner reminder interval:** 1 day
- **Maximum follow-up reminders:** 2
- **Escalation policy:** Safety-contact gate
- **Safety contacts:** three people with checked, independent addresses
- **Required confirmations:** 2
- **Safety response window:** 2 days
- **Safety reminder interval:** 1 day
- **Maximum safety reminders:** 1
- **Postpone after confirmation:** a deliberately reviewed duration, commonly the normal check interval
- **Recipients:** only people who need the configured message

Why use a quorum: one mistaken or compromised safety response cannot postpone the monitor alone. The tradeoff is availability—if two people cannot respond, the safety gate runs to its deadline even if one person confirms.

For genuinely time-critical risk, a multi-day email workflow may be the wrong mechanism. Use a professional or emergency response arrangement instead of compressing every Pulse field to its minimum.

## Configure the monitor step by step

1. Create each person under **Contacts**.
2. Review every address, select the intended notification language, tick the local address-confirmation box, and save.
3. Create or open the monitor and set its timing under **Schedule**.
4. Open **Recipients**, add the contacts who should receive the final notification, and open each dedicated recipient page.
5. On each recipient page, choose the default message or enable a personal override. Review the localized preview.
6. Assign any documents that should belong to that recipient in a future secure portal. In 0.7.0 these assignments do not grant access and no document link is emailed.
7. Open **Escalation** and choose Direct or Safety-contact gate.
8. For a gate, select only checked safety contacts, set the confirmation quorum, response window, reminders, and postponement duration.
9. Open **Review & activation** and resolve every warning.
10. Save, then use **Profile → Notifications** to send a successful SMTP test.

## Write a useful recipient message

A recipient email should be understandable without hidden context. Include:

- who configured the message
- why the recipient is receiving it
- what the recipient should do next
- which actions they should not take based only on the automated email
- another way to verify the situation when appropriate

Avoid putting passwords, recovery keys, document secrets, or highly sensitive personal data in the message. Pulse stores message text unencrypted at rest, and the final email is copied to the mail provider and recipient mailbox.

## Rehearse safely

Use test contacts and non-sensitive wording for the first rehearsal:

1. Confirm that the once-per-minute cron trigger is working.
2. Confirm the owner receives a profile test and due notice.
3. In a non-production deployment with `PULSE_DEBUG=true`, use **Force due now** and **Send due notification now**.
4. Read the confirmation warning before using **Send recipient notification now**. That action bypasses remaining wait periods and sends real recipient mail.
5. Confirm the recipient page records queued, sent, or failed delivery history.
6. For a safety-gated rehearsal, let the normal scheduler issue the safety request so the link and explicit response behavior are exercised.
7. Retry one deliberately failed, non-sensitive message from **Profile → Notifications** after correcting the cause.

Never rehearse with a message that could alarm a real recipient.

## Review after life changes

Review active monitors whenever relationships, addresses, health, travel, legal arrangements, or intended actions change. Editing a recipient changes future releases only; an already staged address and message snapshot remains immutable. A message already accepted by SMTP cannot be recalled by Pulse.

Pause monitors before planned periods in which you should not be expected to check in. Resume starts a fresh interval from that moment.
