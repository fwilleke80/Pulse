# Pulse User Guide

Pulse helps you prepare important information that will be delivered to trusted contacts if you stop checking in.

The system allows you to define monitors, assign contacts, and attach documents.

---

## Basic Concepts

Understanding Pulse requires a few key concepts.

### Contact

A **contact** is a person who may receive notifications and documents if a monitor eventually triggers.

Examples:

- family members
- close friends
- lawyers
- colleagues

Each contact can include:

- name
- email address
- phone number
- notes

---

### Monitor

A **monitor** watches your activity.

If you stop confirming that you are active for a certain period, the monitor will eventually trigger.

Examples:

- a weekly activity check
- a long-term inactivity monitor
- a travel safety monitor

Each monitor can have multiple contacts assigned.

---

### Monitor Contact

A **monitor contact** is the assignment of a contact to a monitor.

This allows a single contact to participate in multiple monitors.

---

### Document

Documents are files or text notes attached to a monitor.

Examples:

- account instructions
- legal documents
- letters to loved ones
- emergency contact lists

Documents belong to the **monitor**, not directly to contacts.

---

### Document Recipients

Each document can be assigned to specific monitor contacts.

This allows you to control who receives which information.

For example:

- one contact may receive legal documents
- another contact may receive personal messages

---

## Getting Started

### Step 1 – Create Contacts

Before creating monitors, add the people you want to notify.

Go to:

Contacts → New Contact

Enter the contact’s details and save.

---

### Step 2 – Create a Monitor

Create a monitor that defines when a check-in is required.

Go to:

Monitors → New Monitor

Configure:

- title
- description
- monitoring interval
- assigned contacts

---

### Step 3 – Upload Documents

Open the monitor and upload documents.

Each document can be assigned to one or more monitor contacts.

Example:

Document: emergency-instructions.pdf
Recipients: Alice, Bob

---

### Step 4 – Assign Recipients

For each document, choose which contacts should receive it.

This allows different people to receive different information.

---

## Editing and Managing Documents

Inside the monitor edit page you can:

- upload new documents
- change recipient assignments
- download documents
- delete documents

---

## Languages

Pulse currently supports:

- English
- German

You can switch the interface language using the language selector.

---

## Health Page

A simple health page is available at:

/health

This page reports:

- database status
- PHP version

It is mainly intended for administrators.

---

## Planned Features

Future versions of Pulse will add:

- periodic check-in confirmations
- email reminders
- automatic notifications to contacts
- escalation workflows
- background processing via cron

These features will turn Pulse into a fully automated monitoring and notification system.

---

## Important Note

Pulse currently provides the **preparation infrastructure**.

Notification and check-in automation will be introduced in future versions.
