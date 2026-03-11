# Emergency Notifier App

## Idea

It should be a kind of emergency notification service made to notify my family & loved ones in case something happens to me.

I can log in, and set up emergency contacts (Name & eMail). I can also set up grace periods. This means: If I set up a grace period of e.g. 1 month, the web app will send me an email once a month. The email contains a link that I need to click on to indicate I'm still alive. If I fail to click the link within a certain period of time after the mail has been sent (e.g. a week), the mail to me will be sent again.

If, after a number of times, I still didn't click the link, the emergency contacts assigned to this grace period will be notified. Each emergency contact gets a personalised email for a grace period (meaning: I can write a personalised message for each emergency contact assigned to this grace period). The notification mail contains a link that the emergency contact person can click on. The link will open a document that I have written/uploaded for this emergency contact in this grace period (e.g. a personal goopdbye letter, my testament, et cetera). 

Setting up different grace periods will allow me to e.g. use a longer period for when I die, and a shorter period for if I disappear during a vacation, or so.

## Stack

* PHP 8+
* MySQL / MariaDB
* Server-rendered HTML
* Minimal JavaScript
* Cron-driven scheduler
* SMTP mail delivery

## Goal

A low-maintenance personal emergency notification web app with:

* login for the owner
* emergency contacts
* multiple grace periods / scenarios
* periodic check-in emails
* reminder retries
* escalation to emergency contacts
* per-contact personalised messages
* protected document access via secure links
* pause mode
* test mode
* audit log

## Recommended Approach

Build it here step by step as a small monolithic PHP application.

Use a simple structure:

```text
/app
  /Controllers
  /Models
  /Services
  /Views
  /Repositories
  /Mail
/Security
  /Support
/public
/storage
  /uploads
  /logs
  /tmp
/config
/bin
/database
```

## Core Modules

1. Authentication
2. Dashboard
3. Contacts management
4. Grace period management
5. Contact assignment per grace period
6. Personalised messages per contact/grace period
7. Document upload and protected document delivery
8. Check-in confirmation flow
9. Scheduler / cron worker
10. Mail queue and mail log
11. Pause / resume mode
12. Test mode
13. Audit log

## Design Principles

* boring over clever
* one cron entry point
* one mailer service
* one scheduler service
* all important actions logged
* token-based access for all public links
* document files stored outside public web root
* code structured so additional confirmation channels can be added later

## Extensibility

Confirmation channels should be abstracted behind an interface from the start, even if v1 only uses email.

Possible later channels:

* backup email
* SMS
* push notification
* app-based confirmation

## Proposed Next Steps

1. Define the database schema
2. Define the scheduler state machine
3. Create the project folder structure
4. Implement base infrastructure
5. Implement CRUD for contacts and grace periods
6. Implement token system
7. Implement mail flow
8. Implement cron worker
9. Implement document delivery
10. Add test mode and event log

## Generated Database Files

Initial database files will live in:

```text
/database
  /schema.sql
  /seed.sql
  /DATABASE.md
  /migrations
```

These files define the initial Pulse schema, optional seed data, and future migration structure.
