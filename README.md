# Pulse

**Current stable release: 1.1.6**

Pulse is a private, self-hosted check-in and notification system for situations in which something could happen to you and the people who need to know might otherwise not be informed for some time. You might use it to make sure family, friends, or other trusted people are contacted if you die or become seriously ill, or as an additional safety measure while travelling alone, going on an expedition, or spending time somewhere where help may be difficult to reach.

You decide how often you expect to check in. As long as you continue to do so, nothing happens. If you stop responding, Pulse first tries to reach you and, if necessary, can involve safety contacts and eventually notify the recipients you chose in advance. Recipient notifications can lead to a private portal containing messages and documents prepared for that recipient.

Pulse is not an emergency-response service. Its timing depends on your cron schedule, mail delivery, and the availability of the server on which it runs.

## Requirements

- PHP 8.4+
- PDO MySQL, Fileinfo, JSON, and OpenSSL PHP extensions
- MySQL 8+ or MariaDB 10.6+
- Apache with `mod_rewrite`, or equivalent front-controller routing on another web server
- the ability to run a scheduled job by URL or command line
- HTTPS for production use

The web server must expose only `public/`. For the built-in routing used by Pulse 1.1, deploy it at the root of its host or virtual host, for example `https://pulse.example.com/`, rather than below a URL prefix such as `/pulse/`.

## Quick installation

1. Create an empty MySQL/MariaDB database and database user.
2. Upload the complete Pulse project and point the website root at `public/`.
3. Make the project root writable enough for PHP to create `.env`, and make `storage/` writable.
4. Open `/install.php` in a browser.
5. Follow the guided installer.
6. Pulse verifies the installation and attempts to remove `public/install.php` automatically. If the server cannot remove it, delete the file manually before using Pulse.
7. Log in, send a test from **Administration → Mail**, and configure cron under **Administration → Cron**.

The installer creates `.env`; a normal installation does not require manual dotenv editing.

For reliable notifications, configure normal sender authentication such as SPF, DKIM, and DMARC where applicable. Also add the configured Pulse sender address or domain to safe-sender/allowlist rules on mailboxes and mail servers you control.

See [docs/INSTALLATION.md](docs/INSTALLATION.md) for the complete installation, permissions, SMTP, cron, verification, and update procedure.

When preparing source for deployment, generate `config/version.php` before uploading:

```bash
python3 tools/write_version.py
```

## First steps after installation

A useful first rehearsal is deliberately small:

1. Add one or two test contacts.
2. Create a test monitor.
3. Add at least one recipient and optionally a safety contact.
4. Create a harmless text document and assign it to a recipient.
5. Review the monitor's messages and escalation settings.
6. Verify a successful SMTP test and cron run.
7. Rehearse the flow with non-sensitive test addresses before relying on Pulse for a consequential monitor.

The [User guide](docs/USER_GUIDE.md) explains the interface and lifecycle. The [Monitor tutorial](docs/MONITOR_TUTORIAL.md) gives example timings and rehearsal guidance.

## Administration

Users with the `administrator` role have access to **Administration**, organized into responsive **General**, **Security**, **Files**, **Mail**, **Cron**, and **Installation** tabs. Runtime settings are written directly to the root `.env` file. Configuration problems are highlighted in the affected tabs.

## Account security and quick check-in

Pulse 1.1.6 includes passkeys as a general account authentication method. Register passkeys under **Profile → Account security** and use them on the normal login page. The security-method storage is deliberately generic so later releases can add additional methods or second-factor policies without tying account authentication to passkeys alone.

Administrators can enable **Passkey quick check-in** under **Administration → Security**. This is the lowest-effort way to perform routine check-ins: open the link in an owner reminder, authenticate with the passkey available on that device, and Pulse immediately performs the same global action as **Check in now** for all active monitors. The email link itself is not authentication, and normal password login remains available as fallback.

For quick check-in to stay genuinely convenient, make sure a Pulse passkey is **available** on every phone, tablet, or computer you expect to use. Password managers such as iCloud Keychain may synchronize the same passkey across several devices automatically, so do not create duplicates unnecessarily. If a device cannot access an existing passkey, register another one under **Profile → Account security**. Test quick check-in from the devices you intend to use during setup.

Owner reminder templates normally use `{quickcheckin}`, which expands to the localized quick-check-in Markdown link when enabled and to nothing when disabled. `{quickurl}` remains available for custom link wording and falls back to the normal `{url}` login URL when quick check-in is disabled.

Passkeys require HTTPS and a stable Pulse hostname in normal deployment.

## Optional check-in locations

Each monitor can independently request a one-time browser location during check-in. Enabling the setting asks the current device for permission; the browser decides whether that grant is retained. Pulse never tracks continuously, and check-in always continues if location is denied or unavailable.

Recorded owner-history locations link to OpenStreetMap. A second, separate monitor option can publish a bounded last-known trail of 1–20 points in the authenticated recipient portal after escalation. That trail is frozen with the release and cannot gain later check-ins. Map tiles load only when a recipient explicitly opens the map. Positions and straight lines are approximate and do not replace rescue or emergency services.

## Documentation

- [Installation and updates](docs/INSTALLATION.md)
- [User guide](docs/USER_GUIDE.md)
- [Monitor tutorial](docs/MONITOR_TUTORIAL.md)
- [Security model](docs/SECURITY.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Release audit guide](docs/AUDIT_GUIDE.md)
- [Roadmap](docs/ROADMAP.md)
- [Changelog](CHANGELOG.md)

## License

Pulse is released under the MIT License. See [docs/LICENSE](docs/LICENSE).
