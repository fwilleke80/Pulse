# Pulse

**Current stable release: 1.0.0**

Pulse is designed for situations in which something could happen to you and the people who need to know might otherwise not be informed for some time. You might use it to make sure family, friends, or other trusted people are contacted if you die or become seriously ill, or as an additional safety measure while travelling alone, going on an expedition, or spending time somewhere where help may be difficult to reach.

You tell Pulse how often you expect to check in. As long as you continue to do so, nothing happens. If you stop responding, Pulse first tries to reach you and, if necessary, eventually contacts the people you selected and sends them the messages you prepared in advance.

## Requirements

- PHP 8.4+
- PDO MySQL, Fileinfo, JSON, and OpenSSL PHP extensions
- MySQL 8+ or MariaDB 10.6+
- Apache with `mod_rewrite`, or equivalent routing on another web server
- ability to run scheduled jobs
- HTTPS in production

## Installation

1. Create an empty MySQL/MariaDB database and user.
2. Upload the complete Pulse project and point the website root at `public/`.
3. Make the project root writable enough for PHP to create `.env`, and make `storage/` writable.
4. Open `/install.php` in a browser.
5. Follow the guided checks for database, Pulse settings, first administrator, and optional SMTP.
6. After final verification Pulse attempts to delete `public/install.php` automatically. If the server forbids this, delete it manually before using Pulse.
7. Log in, test mail under **Administration → Mail**, and configure the cron job shown by the installer.

The installer creates `.env`; normal installations do not require manual dotenv editing.

When deploying directly from a source checkout, run `python3 tools/write_version.py` before uploading so `config/version.php` contains the correct release. A packaged release already contains this file.

See [docs/INSTALLATION.md](docs/INSTALLATION.md) for the full deployment, permissions, SMTP, cron, verification, and update procedure.

For reliable notifications, configure normal sender authentication (SPF/DKIM/DMARC where applicable) and add the configured Pulse sender to safe-senders/allowlists or whitelists on mailboxes and servers you control.

## Administration

Administrator-only application settings are organized into responsive **General**, **Security**, **Files**, **Mail**, **Cron**, and **Installation** tabs. Runtime settings are written directly to the root `.env` file, and configuration problems are highlighted in the relevant tabs and health summary.

The application name is always **Pulse**. The public base URL and database credentials are created by the installer and shown read-only afterward because changing them casually could break bootstrapping or absolute links.

## Documentation

- [Installation and updates](docs/INSTALLATION.md)
- [User guide](docs/USER_GUIDE.md)
- [Monitor tutorial](docs/MONITOR_TUTORIAL.md)
- [Security model](docs/SECURITY.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Changelog](CHANGELOG.md)

## License

Pulse is released under the MIT License. See [docs/LICENSE](docs/LICENSE).
