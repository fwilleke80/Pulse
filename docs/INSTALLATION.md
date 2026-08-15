# Installing Pulse

Pulse includes a guided browser installer. A normal installation no longer requires you to create or edit `.env` manually.

## Requirements

Pulse requires:

- PHP 8.4 or newer
- PDO MySQL, Fileinfo, JSON, and OpenSSL PHP extensions
- MySQL 8+ or MariaDB 10.6+
- a web server that serves the `public/` directory as the site root and routes unknown paths to `public/index.php`
- permission for PHP to write the root `.env` file and `storage/`
- the ability to run a scheduled job, either by URL or command line
- HTTPS for production use

Composer is not required to install or run Pulse.

## 1. Prepare the source

Extract the complete Pulse source archive locally.

When deploying from a source checkout, generate the version file before uploading:

```bash
python3 tools/write_version.py
```

To set the release explicitly:

```bash
PULSE_VERSION=1.0.0 python3 tools/write_version.py
```

A packaged release already contains its generated `config/version.php`. Pulse still starts if that file is missing, but displays **version unavailable**.

## 2. Create an empty database

Create an empty MySQL or MariaDB database and a database user that can create and modify tables inside it.

Do not import `database/schema.sql` manually. The installer applies Pulse's database schema through the migration system.

## 3. Upload Pulse

Upload the complete project. Keep this structure intact:

```text
Pulse/
├── app/
├── config/
├── database/
├── docs/
├── public/
├── storage/
├── tools/
├── .env.example
└── bootstrap.php
```

Set the site's document root to:

```text
Pulse/public/
```

Do **not** expose the complete project directory as the website root. Configuration, logs, and uploaded documents must remain outside the public directory.

For Apache, `public/.htaccess` supplies the required front-controller routing. Other web servers need equivalent routing.

## 4. Set permissions

PHP must be able to create or update:

```text
.env
storage/
storage/logs/
storage/tmp/
storage/uploads/
```

The installer also tries to delete `public/install.php` after successful setup. If the PHP account cannot delete files from `public/`, installation still succeeds, but you must remove that file manually before Pulse will operate normally.

Avoid making the entire project world-writable. Grant only the permissions the PHP/web-server account needs.

## 5. Run the installer

Open:

```text
https://your-pulse-host.example/install.php
```

If Pulse is installed below a URL prefix, open that path instead, for example `https://example.com/pulse/install.php`. The installer preserves the subdirectory in its own navigation and suggests `https://example.com/pulse` as the public base URL.

The installer walks through six stages.

### System

Pulse checks the PHP version, required extensions, writable directories, database setup, `.env` write access, and whether automatic installer removal is possible. Blocking failures must be corrected before continuing.

### Database

Enter the existing database host, port, database name, username, and password. Pulse tests the connection before writing the credentials to `.env`.

### Pulse settings

Confirm:

- the public base URL
- the display timezone
- the default interface language

The installer detects the public base URL from the address used to open it and pre-fills that value as a suggestion. This also accounts for the common case where HTTPS is terminated by a reverse proxy. Change the suggestion only if Pulse will later be available at a different public address.

Pulse generates the remaining safe defaults automatically, including trusted-host configuration, secure-session defaults, login throttling, upload defaults, and a cryptographically random web-cron token.

An HTTPS base URL configures Pulse for production. An HTTP base URL configures a development installation so the application does not pretend that insecure transport is production-safe.

The installer then applies the current Pulse database schema.

### Administrator

Create the first administrator account with a name, email address, and password of at least 12 characters.

This account receives the `administrator` role. The role model is already prepared for later multi-user support.

### Mail

SMTP setup is optional during installation. You can either configure it immediately or skip it and use **Administration → Mail** after login.

If you configure mail during installation, Pulse stores and enables the SMTP configuration. After logging in, still send a test from **Administration → Mail** before relying on notifications.

### Finish

Before declaring success, Pulse verifies:

- required `.env` configuration
- the public URL and timezone
- the database connection
- the migrated schema
- an active administrator account
- completion of every installer stage

Only after successful verification does Pulse attempt to delete `public/install.php`.

If automatic deletion succeeds, use the displayed **Log in to Pulse** link.

If deletion fails, the finish page tells you to delete:

```text
public/install.php
```

Pulse deliberately refuses normal application, web-cron, and command-line notification-worker operation while that file still exists.

## 6. Configure and test mail

If SMTP was skipped, or if you want to review it, log in and open **Administration → Mail**.

Configure the SMTP host, port, encryption, credentials, sender identity, and queue/retry behaviour. Production mail must use STARTTLS or TLS.

Send a test notification. Do not rely on a consequential monitor until that test succeeds.

For mailboxes and mail servers you control, add the configured Pulse sender address to the safe-senders/allowlist (often called a whitelist). If your system supports domain-level allowlisting, you may also allow the sender domain. Where appropriate, ask safety contacts and recipients to allow the Pulse sender as well. This reduces the risk of due notices, safety-contact messages, access codes, or recipient notifications being rejected or classified as Spam. It complements rather than replaces correct SMTP, SPF, DKIM, and DMARC configuration.

## 7. Configure the cron job

The cron job notices due monitors, queues reminders, advances escalation, and sends eligible mail. Run it once per minute.

### Option A: web cron

The installer generates the token and shows the complete web-cron URL on its finish page. You can also review/regenerate the token later under **Administration → Cron**.

The URL has this form:

```text
https://pulse.example.com/cron/cron.php?token=YOUR_SECRET_TOKEN
```

A successful run returns:

```text
OK
```

Treat the complete URL as a credential because the token may appear in hosting, proxy, or server logs.

### Option B: command-line cron

If command-line PHP is available:

```cron
* * * * * cd /path/to/pulse && /usr/bin/php tools/pulse.php notifications:run --limit=25 >/dev/null 2>&1
```

Use either web cron or command-line cron; there is normally no need to configure both.

After a combined cron run completes successfully, **Administration → Cron** shows its timestamp as **Last successful cron run**. A fresh installation warns until the first successful run is recorded, and Pulse flags the status as stale only after more than 24 hours without a successful run. This deliberately allows installations that choose a slower cadence such as hourly cron.

## 8. Verify the live installation

Before creating a real monitor:

1. Sign in with the administrator account.
2. Open the dashboard and resolve any configuration warnings.
3. Open `/health` and `/health/readiness`.
4. Send a successful test from **Administration → Mail**.
5. Run the configured cron once and verify `OK`, or run the command-line worker once.
6. Create a non-sensitive test contact and monitor.
7. Rehearse the notification flow using non-sensitive addresses and wording.

See the [monitor tutorial](MONITOR_TUTORIAL.md) for example configurations and rehearsal guidance.

## Updating Pulse

For releases based on the Pulse 1.0 schema baseline:

1. Back up the database, `.env`, and `storage/uploads`.
2. Extract the new release locally.
3. If deploying from source, run `python3 tools/write_version.py` before uploading.
4. Keep the existing server `.env` and private storage data.
5. Upload the complete new application over the existing installation.
6. Open Pulse in a browser.

Release archives include `public/install.php`. On an initialized installation, the installer recognizes the existing account and only attempts to remove itself; it does not recreate users or configuration. If the server cannot delete it, remove `public/install.php` manually.

After the installer is gone, normal startup applies any schema migrations introduced by the new release automatically.

Finally, send a test from **Administration → Mail** and verify that cron still runs.

`database/schema.sql` is reference documentation and should not be imported over a running installation.
