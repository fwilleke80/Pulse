# Pulse Architecture

This document provides a concise technical overview of Pulse’s current architecture and data model.

It is intended for developers working on the codebase.

---

# High-Level Overview

Pulse is a PHP web application that prepares structured emergency information for future automated delivery.

The application currently supports:

- user authentication
- contact management
- monitor management
- assignment of contacts to monitors
- document upload to monitors
- assignment of documents to specific monitor contacts

The future goal is to add:

- explicit check-ins
- reminder emails
- escalation workflows
- background processing through cron jobs

---

# Architectural Style

Pulse uses a lightweight layered architecture.

## Request flow

```txt
HTTP Request
    ↓
public/index.php
    ↓
Router
    ↓
Controller
    ↓
Repository / Service
    ↓
View
```

## Main layers

### Controllers

Controllers handle request flow and user actions.

Examples:

- AuthController
- ContactController
- MonitorController
- ProfileController
- HomeController
- LanguageController

Controllers should:

- validate request input
- enforce authentication / ownership
- call repositories and services
- prepare view data
- redirect or render views

Controllers should not contain SQL.

### Repositories

Repositories encapsulate database access.

Examples:

- UserRepository
- ContactRepository
- MonitorRepository
- DocumentRepository

Repositories should:

- perform SQL queries
- return rows as arrays
- persist data
- stay focused on one domain area

Repositories should not perform rendering.

### Services

Services contain reusable business logic that is not tied to a single HTTP request.

At the moment, the main example is:

- AuthService

Future service candidates include:

- MailService
- MonitorExecutionService
- CheckinService

### Views

Views are PHP templates under:

app/Views

They receive data from controllers through the View class.

Views should:

- render HTML
- use escaped output by default
- avoid complex application logic

### Core Infrastructure

The app/Core namespace contains reusable framework-like infrastructure:

- Router
- Database
- Session
- View
- Translator
- helpers

## Current Domain Model

The most important entities in Pulse are described below.

### User

A user owns all other top-level data.

A user can have:

- many contacts
- many monitors

### Contact

A contact represents a person who may later receive notifications.

Fields currently include:

- name
- email
- cell phone
- notes

A contact belongs to exactly one user.

A contact can be assigned to multiple monitors.

### Monitor

A monitor represents a rule that watches the user’s activity.

Conceptually, a monitor answers:

> “If I stop confirming my status, who should receive what?”

Fields currently include configuration values such as:

- name
- description
- check interval
- response window
- reminder interval
- max reminders
- paused state

A monitor belongs to exactly one user.

A monitor can have multiple assigned contacts.

### Monitor Contact

`monitor_contacts` is the join table between monitors and contacts.

This is a very important concept in Pulse.

A monitor contact does not just mean:

> “this contact belongs to this monitor”

It also acts as the anchor point for per-recipient configuration.

That is why other tables relate to `monitor_contact_id`.

Examples of things that belong to a monitor contact:

- recipient-specific messages
- recipient assignment for documents

### Contact Message

`contact_messages` stores a message for a specific monitor contact.

This means each assigned contact inside a monitor can receive different text.

That is the correct model, because messaging is recipient-specific.

### Document

A document belongs to a monitor.

This is deliberate.

A document is first attached to the monitor, because the document itself belongs to the overall situation, not inherently to one specific person.

Examples:

- legal instructions
- account information
- a letter
- emergency notes

A document can be stored as:

- uploaded file
- text content

### Document Recipient Assignment

`document_monitor_contacts` connects documents to specific monitor contacts.

This means:

- a document can be sent to one contact
- a document can be sent to several contacts
- the same document does not need to be uploaded multiple times

This is the key reason the document model is:

```txt
Monitor
 ├─ Documents
 └─ Monitor Contacts
      └─ Recipient assignments
```

instead of storing documents separately under each monitor contact.

## Core Data Relationships

The current data model can be understood like this:

```txt
User
 ├─ Contacts
 └─ Monitors
      ├─ Monitor Contacts
      │    └─ Contact Messages
      └─ Documents
           └─ Document Recipient Assignments
```

Or more explicitly:

```txt
users
 ├─ contacts
 └─ monitors
      ├─ monitor_contacts
      │    └─ contact_messages
      └─ documents
           └─ document_monitor_contacts
```

## Why Documents Belong to Monitors

This is one of the most important design decisions in the current codebase.

A document belongs to the monitor, not directly to a monitor contact.

Why:

- the same document may be relevant to multiple recipients
- uploading the same file multiple times would be redundant
- recipient assignment should be flexible

So the workflow is:

1. upload document to monitor
2. choose which monitor contacts should receive it

This is cleaner than:

1. upload document separately inside each recipient section

## Current Request Ownership Rules

Whenever an object is accessed, ownership must be validated through the user.

Examples:

- a contact must belong to the current user
- a monitor must belong to the current user
- a document must belong to a monitor owned by the current user

This is especially important for:

- edit routes
- delete routes
- document download routes

## File Storage Model

Uploaded files are stored under:  
`storage/uploads/monitor-documents`

Important rules:

- the stored filename is generated uniquely
- the original filename is preserved separately as metadata
- users never access files by direct filesystem path
- downloads are always routed through application logic

This avoids:

- filename collisions
- accidental overwrite
- leaking filesystem paths

Current Routing Style

Routes are defined centrally in:  
`public/index.php`

The router dispatches to controller methods.

Examples:

- `GET /contacts`
- `POST /contacts/create`
- `GET /monitors/edit`
- `POST /monitors/documents/upload`

Current route style is explicit and simple.

## Translation Model

Pulse uses a lightweight translation system.

Language files live in:

```txt
app/Lang/en.php
app/Lang/de.php
```

Helpers include:

- `__()` for translated output
- `e__()` for escaped translated output
- `e()` for escaped plain strings

The current locale is stored in the session and applied during bootstrap.

## Versioning Model

Pulse does not hardcode the application version in `config/app.php`.

Instead, version information is generated locally using:  
`tools/write_version.py`

This writes the derived version string to:  
`config/version.php`

This approach fits the deployment model where the `.git` directory is not present on the server.

## What Is Not Implemented Yet

The following important parts are still planned:

### Check-in

A monitor currently has timing-related configuration, but there is not yet a completed check-in workflow.

Planned check-in sources include:

- manual confirmation in the UI
- secure confirmation through an email link

### Monitor Execution

Pulse does not yet automatically evaluate monitors.

That future logic will likely:

- detect overdue monitors
- send reminders
- trigger escalation
- log monitor events

### Mail Delivery

Mail delivery is not yet implemented.

Later, mail generation will need to combine:

- monitor
- assigned contact
- contact-specific message
- documents assigned to that monitor contact

### Cron Processing

Scheduled execution is planned for future versions.

## Recommended Future Architectural Direction

As the project grows, the next logical service layer additions are:

- CheckinService
- MailService
- MonitorExecutionService

These services should keep business rules out of controllers.

Likewise, future event/audit logging will likely justify its own repository or service.

## Design Principles for Future Work

When extending Pulse, these rules should remain true.

### 1. Keep ownership checks strict

Never access a monitor, contact, or document without validating user ownership.

### 2. Keep business logic out of views

Views should remain presentation-only.

### 3. Keep SQL inside repositories

Controllers should orchestrate, not query the database directly.

### 4. Prefer explicit data models over implicit conventions

If the real-world feature has a relationship, it should be represented in the schema.

### 5. Avoid duplication in document delivery

Documents should continue to be attached to monitors and assigned to recipients separately.

That model is both flexible and efficient.

## Summary

Pulse currently consists of a solid configuration platform built around four central concepts:

- contacts
- monitors
- monitor contacts
- documents

The most important structural rule is:

> Documents belong to monitors, and recipient delivery is assigned separately through monitor contacts.

This model gives Pulse a clean foundation for the next development steps:

- check-in
- mail delivery
- reminder processing
- cron execution
