# Pulse

Pulse is a self-hosted personal dead man’s switch that makes sure trusted people receive important information and documents if you stop checking in.

You define recurring check-ins, reminders, escalation rules, recipients, optional safety contacts, messages, and recipient-specific documents.

## Requirements

- PHP 8.4 or 8.5
- PDO MySQL, Fileinfo, and OpenSSL PHP extensions
- MySQL 8+ or MariaDB 10.6+
- Apache with `mod_rewrite`, or equivalent routing on another web server
- Ability to run Cron jobs
- HTTPS in production

## Installation

1. Extract the complete source archive locally into the Pulse project directory.
2. Before uploading any PHP files, run `python3 tools/write_version.py`. This generates `config/version.php`; upload that generated file with the application. In a tagged Git checkout the script derives the version from Git, while a packaged archive retains its packaged version. Set `PULSE_VERSION=0.7.1` for an explicit release value when needed.
3. Copy `.env.example` to `.env` and enter the real URL and database credentials.
4. Create an empty database.
5. Upload the project and ensure `storage/logs`, `storage/tmp`, and `storage/uploads` are writable by PHP but not publicly accessible.
6. Configure the site’s document root as the project’s `public/` directory.
7. Serve the application exclusively over HTTPS.
8. Open Pulse in a browser. The first request creates and migrates the database automatically.
9. Configure SMTP, enable mail, and send a test from **Profile → Notifications**.
10. Install either the web-cron URL or command-line cron job.

If `config/version.php` is missing, Pulse remains operational and displays **version unavailable** instead of failing. Generate the file before deployment so asset cache keys and the displayed release are accurate.

## Documentation

- [Installation and upgrading](INSTALLATION.md) — server requirements, deployment, `.env`, SMTP, cron, verification, and updates
- [User guide](USER_GUIDE.md) — contacts, monitors, check-ins, recipients, safety contacts, messages, documents, and notifications
- [Monitor tutorial](MONITOR_TUTORIAL.md) — practical examples for choosing monitor timing and escalation rules
- [Security model](SECURITY.md) — security assumptions, protections, limitations, and production checklist
- [Architecture](ARCHITECTURE.md) — application structure and the main runtime flows
- [Changelog](../CHANGELOG.md) — release history

## License

Pulse is released under the MIT License. See [docs/LICENSE](docs/LICENSE).
