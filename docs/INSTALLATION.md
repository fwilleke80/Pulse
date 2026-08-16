# Installing Pulse

Pulse includes a guided browser installer. A normal installation does not require you to create or edit `.env` manually.

## Requirements

Pulse requires:

- PHP 8.4 or newer
- PDO MySQL, Fileinfo, JSON, and OpenSSL PHP extensions
- MySQL 8+ or MariaDB 10.6+
- a web server that exposes `public/` as the site document root and routes unknown application paths to `public/index.php`
- permission for PHP to create/update the root `.env` file and write to `storage/`
- the ability to run a scheduled job by URL or command line
- HTTPS for production use

Composer is not required to install or run Pulse.

### URL layout

Pulse 1.2 expects to run at the root of its host or virtual host, for example:

```text
https://pulse.example.com/
```

Point that host directly at Pulse's `public/` directory. The built-in router does not support mounting Pulse below a URL prefix such as `https://example.com/pulse/`.

## 1. Prepare the release

Extract the complete Pulse release archive locally.

A packaged release already contains the generated `config/version.php` file. If you are deploying directly from a source checkout instead, generate it before uploading:

```bash
python3 tools/write_version.py
```

To set a release explicitly:

```bash
PULSE_VERSION=1.2.1 python3 tools/write_version.py
```

Pulse still starts if the generated version file is missing, but displays **version unavailable**.

## 2. Create an empty database

Create an empty MySQL or MariaDB database and a database user that can create and modify tables inside it.

You need:

- database host
- port, normally `3306`
- database name
- username
- password

Do not import `database/schema.sql` manually. The installer applies Pulse's schema through the migration system.

## 3. Upload Pulse

Upload the complete project and keep its directory structure intact:

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

Set the website's document root to:

```text
Pulse/public/
```

Do **not** expose the complete project directory as the website root. Configuration, logs, temporary files, and uploaded documents must remain outside the public directory.

For Apache, `public/.htaccess` supplies the front-controller routing. Other web servers need equivalent routing.

## 4. Set permissions

The PHP/web-server account must be able to create or update:

```text
.env
storage/
storage/logs/
storage/tmp/
storage/uploads/
```

The installer also attempts to delete `public/install.php` after successful setup. If the PHP account cannot delete files from `public/`, installation can still finish, but you must remove that file manually before Pulse will operate normally.

Do not make the entire project world-writable. Grant only the permissions required by the PHP/web-server account.

## 5. Run the installer

Open:

```text
https://pulse.example.com/install.php
```

The installer walks through six stages.

### System

Pulse checks:

- PHP version
- required PHP extensions
- `.env` write access
- `storage/` and its required subdirectories
- availability of the migration files
- whether automatic installer removal is possible

Blocking failures must be corrected before continuing. Inability to self-delete the installer is reported but is not itself a blocking failure because you can remove the file manually.

### Database

Enter the database host, port, database name, username, and password. Pulse tests the connection before storing the credentials in `.env`.

The database itself must already exist; Pulse creates and maintains its own tables inside it.

### Pulse settings

Confirm:

- **Public base URL**;
- **Time zone**;
- **Default language**.

The installer suggests the public address from the URL used to open it. For a normal production installation this should look like:

```text
https://pulse.example.com
```

Do not add a trailing slash, query string, credentials, or a URL path.

The timezone field is a standard IANA timezone selector. It affects how Pulse displays dates and times; lifecycle timestamps continue to be stored in UTC.

Pulse generates the remaining safe defaults automatically, including trusted-host configuration, secure-session defaults, login throttling, upload defaults, and a cryptographically random web-cron token.

An HTTPS public URL configures Pulse for production defaults. An HTTP URL configures a development installation.

Passkeys and passkey quick check-in depend on the production hostname and HTTPS. After installation, register a passkey under **Profile → Account security**, then verify that a Pulse passkey is available on every device you intend to use for routine quick check-ins. Password managers such as iCloud Keychain may synchronize the same passkey automatically; create additional credentials only where necessary. Rehearse quick check-in from those devices before relying on the feature.

The installer then applies the current Pulse database schema.

### Administrator

Create the first administrator account with:

- name
- email address
- password of at least 12 characters

The account receives the `administrator` role.

### Mail

SMTP configuration is optional during installation. You can configure it now or skip it and finish under **Administration → Mail** after logging in.

Even when SMTP is configured during installation, send a test from Administration before relying on Pulse.

### Finish

Before declaring success, Pulse verifies the resulting configuration, database connection/schema, administrator account, and installer state.

Only after successful verification does Pulse attempt to delete:

```text
public/install.php
```

If deletion succeeds, use **Log in to Pulse**.

If deletion fails, remove `public/install.php` manually. Pulse deliberately refuses normal application, web-cron, and command-line notification-worker operation while the installer still exists.

## 6. Configure and test mail

Log in as the administrator and open **Administration → Mail**.

Configure or review:

- mail enabled/disabled state
- SMTP host and port
- STARTTLS/TLS mode
- username and password when required
- From address and sender name
- SMTP timeout
- retry and worker behavior

Production mail must use STARTTLS or TLS.

Send a test notification and confirm that it arrives. Do not rely on a consequential monitor until this succeeds.

### Help important mail reach the inbox

For mailboxes and mail servers you control, add the configured Pulse sender address to safe-sender/allowlist rules (often called a whitelist). Where useful, allow the sender domain as well. Ask safety contacts and final recipients to allow the Pulse sender when that is practical.

Allowlisting reduces the chance that due notices, safety-contact messages, access codes, or recipient notifications are rejected or classified as Spam. It complements rather than replaces correct SMTP setup and SPF, DKIM, and DMARC configuration.

## 7. Configure cron

Cron is what makes Pulse advance without somebody visiting the website. It detects due monitors, creates eligible reminders/escalation work, and processes the mail queue.

### Recommended frequency

**Once per minute is recommended** for predictable timing.

A slower schedule also works, but every due time, reminder, safety deadline, escalation, retry, and queued mail may be delayed until the next cron run. For example, an hourly cron can add almost an hour of latency to any stage.

Administration intentionally marks cron **Stale** only after more than 24 hours without a successful combined run, so installations with a deliberately slower cadence are not immediately treated as broken.

### Option A: web cron

The installer generates a secret token and shows the web-cron URL. You can later regenerate the token under **Administration → Cron**.

The URL has this form:

```text
https://pulse.example.com/cron/cron.php?token=YOUR_SECRET_TOKEN
```

A successful run returns:

```text
OK
```

Treat the complete URL as a credential. The token may appear in hosting, proxy, browser, or server logs if handled carelessly.

### Option B: command-line cron

If command-line PHP is available:

```cron
* * * * * cd /path/to/pulse && /usr/bin/php tools/pulse.php notifications:run --limit=25 >/dev/null 2>&1
```

Use either web cron or command-line cron; normally there is no reason to configure both.

### Verify cron

After a complete scheduler and queue run finishes successfully, **Administration → Cron** displays **Last successful cron run**.

A fresh installation shows **Never** until the first successful run. More than 24 hours without one is marked **Stale**.

## 8. Verify the installation

Before creating a real consequential monitor:

1. Sign in with the administrator account.
2. Open **Administration** and resolve configuration warnings.
3. Send a successful test from **Administration → Mail**.
4. Run cron and verify that **Last successful cron run** updates.
5. Open `/health` and, while signed in, `/health/readiness`.
6. Add non-sensitive test contacts.
7. Create a test monitor and a harmless text document.
8. Rehearse the expected notification path with test email addresses.

See the [User guide](USER_GUIDE.md) and [Monitor tutorial](MONITOR_TUTORIAL.md).

## Updating Pulse after 1.0

Starting with the 1.0 baseline, future releases are intended to update in place through permanent numbered migrations.

Before updating:

1. Back up the database.
2. Back up `.env`.
3. Back up `storage/uploads/` and any other private data you need to preserve.

Then:

1. Extract the complete new Pulse ZIP locally.
2. Upload the complete contents over the existing installation, merging directories and overwriting application files without deleting the existing server tree first.
3. Keep the server's existing `.env`, `storage/`, and uploaded documents.
4. Open Pulse normally. Because the release contains `public/install.php`, Pulse first verifies the existing initialized account and attempts to remove the installer without recreating configuration, users, or database data.
5. If the web server cannot remove `public/install.php`, delete that one file manually and open Pulse again.
6. Normal startup applies each new numbered schema migration automatically.

An uploaded `public/install.php` temporarily locks normal operation only until the existing installation has been verified and the installer removes itself. Invalid database credentials deliberately stop that verification instead of modifying the installation.

Do not remove the existing `.env`, enter the fresh-install workflow, or import `database/schema.sql` as part of an update.

Finally:

- log in
- send a test from **Administration → Mail**
- verify **Last successful cron run**
- check the changelog for release-specific notes

`database/schema.sql` is reference documentation and must not be imported over a running installation.
