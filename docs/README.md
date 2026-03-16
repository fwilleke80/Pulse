# Pulse

Pulse is a personal safety web application that monitors user inactivity and can later notify trusted contacts if the user stops checking in.

The project currently implements the **configuration and preparation layer** of the system: users can define monitors, manage contacts, attach documents, and configure who should receive which information.

Automatic notifications and check-in processing will be added in later versions.

---

## Current Features

Pulse currently supports the following functionality.

### Authentication

- User login and logout
- Session-based authentication
- Password verification
- Profile editing and password change

### Dashboard

The dashboard provides a quick overview of:

- total contacts
- total monitors

Future versions will also display monitor status.

### Contacts

Users can manage contacts who may later receive notifications.

Supported operations:

- create contact
- edit contact
- delete contact

Each contact may include:

- name
- email
- phone number
- optional notes

### Monitors

A **monitor** represents a rule that watches the user's activity.

Each monitor has:

- title
- description
- monitoring interval
- assigned contacts

Contacts are assigned using **monitor-contact assignments**, allowing the same contact to participate in multiple monitors.

### Documents

Documents can be uploaded to monitors.

Documents may be:

- uploaded files
- text content stored in the database

Documents belong to the **monitor**, not directly to contacts.

### Document Recipient Assignment

Each document can be assigned to specific monitor contacts.

This is implemented through the table:  
`document_monitor_contacts`

This model allows a document to be delivered to:

- one contact
- multiple contacts
- all contacts assigned to a monitor

### Document Download

Uploaded files can be downloaded through the monitor edit page.

Access is validated so that:

- the user owns the monitor
- the document belongs to that monitor

Files are delivered using their original filenames.

### Language Support

The interface supports:

- English
- German

Language switching is handled by the `LanguageController`.

### Health Endpoint

A health endpoint is available at:  
`/health`

It reports:

- database connectivity
- PHP version

### Version Display

Pulse displays a version string generated from the Git repository.

A helper script derives the version from:

- the latest tag
- the current commit hash

The version is written to:  
`config/version.php`

---

## Planned Features

The following features are planned for upcoming versions.

### Check-in System

Users will confirm they are still active at regular intervals.

Possible confirmation methods:

- confirmation through the web interface
- confirmation through secure email links

Each confirmation resets the monitor timer.

### Email Notifications

If a monitor expires without confirmation:

- reminder emails will be sent to the user
- assigned contacts will eventually receive notifications

Contacts will receive:

- their configured message
- documents assigned to them

### Escalation Logic

Future versions may support escalation steps such as:

- repeated reminders
- staged contact notification
- configurable delays

### Cron Processing

A scheduled background job will evaluate monitors and trigger notifications when needed.

---

## Project Structure

```txt
app/
Controllers/
Core/
Lang/
Repositories/
Services/
Views/

config/
app.php
version.php

database/
schema.sql
migrations/

docs/
README.md
USER_GUIDE.md

public/
index.php
assets/

storage/
uploads/

tools/
write_version.py
```

---

## Key Components

### Controllers

Controllers handle request routing and application logic.

Examples:

- `AuthController`
- `ContactController`
- `MonitorController`
- `ProfileController`
- `HomeController`

### Repositories

Repositories encapsulate database access.

Examples:

- `UserRepository`
- `ContactRepository`
- `MonitorRepository`
- `DocumentRepository`

### Core Infrastructure

Core components include:

- Router
- Database abstraction
- View renderer
- Session manager
- Translator

---

## Database Structure

Important tables include:

```txt
users
contacts
monitors
monitor_contacts
contact_messages
documents
document_monitor_contacts
```

### Document Model

Documents belong to monitors:  
`ocuments.monitor_id → monitors.id`

Recipient assignment is handled through:  
`document_monitor_contacts`

---

## Installation

### Requirements

- PHP 8.1+
- MySQL or MariaDB
- Web server with PHP support

### Setup

1. Create a database.
2. Import the schema:  
    database/schema.sql
3. Configure the application:  
    config/app.php
4. Ensure the upload directory exists and is writable:  
    storage/uploads/monitor-documents
5. Generate the application version:  
    python tools/write_version.py

---

## Development

### Version Generation

The script:  
`tools/write_version.py`

reads the Git repository and generates:  
`config/version.php`

This file contains the version string displayed in the UI.

---

## Security Notes

Uploaded documents are stored using **generated unique filenames**.

Original filenames are stored only as metadata.

All document access is validated to ensure that:

- the requesting user owns the monitor
- the document belongs to that monitor
