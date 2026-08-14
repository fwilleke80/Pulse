# Pulse

Pulse is designed for situations in which something could happen to you and the people who need to know might otherwise not be informed for some time. You might use it to make sure family, friends, or other trusted people are contacted if you die or become seriously ill, or as an additional safety measure while travelling alone, going on an expedition, or spending time somewhere where help may be difficult to reach.

You tell Pulse how often you expect to check in. As long as you continue to do so, nothing happens. If you stop responding, Pulse first tries to reach you and, if necessary, eventually contacts the people you selected and sends them the messages you prepared in advance.

## Requirements

- PHP 8.4 or 8.5
- PDO MySQL, Fileinfo, and OpenSSL PHP extensions
- MySQL 8+ or MariaDB 10.6+
- Apache with `mod_rewrite`, or equivalent routing on another web server
- Ability to run Cron jobs
- HTTPS in production

## Installation

1. Extract the complete source archive locally into the Pulse project directory.
2. Before uploading any PHP files, run `python3 tools/write_version.py`. This generates `config/version.php`; upload that generated file with the application. In a tagged Git checkout the script derives the version from Git, while a packaged archive retains its packaged version. Set `PULSE_VERSION=0.8.1` for an explicit release value when needed.
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

- [Installation and upgrading](docs/INSTALLATION.md) — server requirements, deployment, `.env`, SMTP, cron, verification, and updates
- [User guide](docs/USER_GUIDE.md) — contacts, monitors, check-ins, recipients, safety contacts, messages, documents, and notifications
- [Monitor tutorial](docs/MONITOR_TUTORIAL.md) — practical examples for choosing monitor timing and escalation rules
- [Security model](docs/SECURITY.md) — security assumptions, protections, limitations, and production checklist
- [Architecture](docs/ARCHITECTURE.md) — application structure and the main runtime flows
- [Changelog](CHANGELOG.md) — release history

## License

Pulse is released under the MIT License. See [docs/LICENSE](docs/LICENSE).
