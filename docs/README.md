# Pulse documentation

The project root [README](../README.md) gives the short overview. The documents in this directory cover installation, everyday use, monitor design, security, and the application architecture.

- [Installation and upgrading](INSTALLATION.md) — server requirements, deployment, `.env`, SMTP, cron, verification, and updates
- [User guide](USER_GUIDE.md) — contacts, monitors, check-ins, recipients, safety contacts, messages, documents, and notifications
- [Monitor tutorial](MONITOR_TUTORIAL.md) — practical examples for choosing monitor timing and escalation rules
- [Security model](SECURITY.md) — security assumptions, protections, limitations, and production checklist
- [Architecture](ARCHITECTURE.md) — application structure and the main runtime flows
- [Changelog](../CHANGELOG.md) — release history

Pulse currently sends recipient message emails, but it does not yet release documents to recipients. Uploaded documents and message text are also not encrypted at rest. See the [user guide](USER_GUIDE.md#documents) and [security model](SECURITY.md#current-limitations) before storing sensitive material.
