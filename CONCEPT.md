# Pulse - Emergency Notifier App

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
