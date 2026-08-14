# Installing Pulse

This guide describes a normal production installation of Pulse and the cron job that drives reminders and escalation.

## Requirements

Pulse requires:

- PHP 8.4 or 8.5
- the PDO MySQL, Fileinfo, and OpenSSL PHP extensions
- MySQL 8+ or MariaDB 10.6+
- a web server that can route requests to `public/index.php` — Apache with `mod_rewrite` is supported by the included `public/.htaccess`
- the ability to run a scheduled job, either by calling a URL or by running a command-line cron job
- HTTPS for production use

Composer is not required to install or run Pulse.

## 1. Prepare the source locally

Extract the complete Pulse source archive on your computer.

If you downloaded the complete source .zip, before uploading any PHP files, generate the version file from the project root. If you are using a release .zip, you can skip this step.

Generating the version file works like this:

```bash
python3 tools/write_version.py
```

This creates `config/version.php`. Upload that file together with the rest of the application.

If you need to set the release value explicitly, use:

```bash
PULSE_VERSION=0.9.0  
python3 tools/write_version.py
```

Pulse still starts if `config/version.php` is missing, but it displays **version unavailable** and cannot use the release number for asset cache keys. A normal deployment should therefore generate the file first.

## 2. Create the database

Create an empty MySQL or MariaDB database and a database user with permission to use it.

You do not need to import `database/schema.sql` manually. Pulse creates and updates its database schema automatically when the application starts.

## 3. Create `.env`

Copy the supplied example file:

```text
.env.example → .env
```

Keep `.env` in the Pulse project root. It contains secrets and must never be placed inside `public/` or committed to a public repository.

At minimum, configure the values for:

```text
PULSE_BASE_URL
PULSE_TRUSTED_HOSTS
PULSE_DB_HOST
PULSE_DB_DATABASE
PULSE_DB_USERNAME
PULSE_DB_PASSWORD
```

For the initial boot, also review the production, cookie, and timezone settings in `.env.example`. In 0.9.0, normal runtime settings can be maintained after sign-in under **Administration** instead of editing `.env` manually. Database credentials remain boot-critical and read-only in Administration until the 0.9.x installer is added.

Process-level environment variables override values from `.env` when both are present. Administration shows a warning when it detects such overrides because editing `.env` cannot replace a process-level value.

## 4. Upload Pulse

Upload the complete project to the server. The project should keep this basic layout:

```text
Pulse/
├── app/
├── config/
├── database/
├── docs/
├── public/
├── storage/
├── tools/
├── .env
└── bootstrap.php
```

The web server's document root must point to:

```text
Pulse/public/
```

Do **not** expose the complete Pulse project directory as the website root. Application code, configuration, `.env`, logs, and uploaded documents belong outside the public web directory.

For Apache, the included `public/.htaccess` routes clean URLs such as `/monitors` to the Pulse front controller. Other web servers need equivalent front-controller routing.

## 5. Set directory permissions

The PHP process must be able to write to:

```text
.env
storage/logs
storage/tmp
storage/uploads
```

`.env` must remain outside the public document root. Pulse 0.9.0 needs write access to it so the administrator can save application settings safely through the web interface. The storage directories must likewise not be publicly accessible.

Avoid making the complete project world-writable. Grant the web-server/PHP account only the specific file and directory permissions it needs.

## 6. Enable HTTPS

Configure the site to use HTTPS before relying on Pulse in production. Set `PULSE_BASE_URL` to the final HTTPS URL and make sure the configured trusted hostname matches it.

## 7. Start Pulse

Open the Pulse URL in a browser.

On the first request, Pulse checks the database and automatically creates or applies the required schema migrations. No separate migration command is required for normal installation.

After signing in, open the dashboard and verify that the application loads without warnings about the database or storage directories.

## 8. Configure email

Pulse depends on email for check-in reminders, safety-contact requests, and recipient notifications. Do not rely on a monitor until SMTP has been tested successfully.

Sign in with the administrator account and open **Administration → Mail**. Configure the SMTP host, port, encryption, credentials, sender identity, and queue/retry behaviour there. Production SMTP must use encrypted transport:

- STARTTLS, commonly on port 587, or
- implicit TLS, commonly on port 465

Enable automatic mail delivery only after those values are correct, save the settings, then send a test notification from the same Mail tab. The test uses the same installation-wide queue and SMTP transport as normal Pulse notifications.

The SMTP password is never displayed back in the form. Leaving the password field blank keeps the current value; clearing it requires the explicit clear option.

If the test fails, fix SMTP before configuring consequential monitors.

## 9. Configure the cron job

The cron job is essential. It is what notices that monitors have become due, queues reminders, advances safety-contact gates, and sends eligible recipient messages.

Run it once per minute.

### Option A: web cron with a secret token

This is intended for hosting providers that can schedule a URL but do not provide command-line cron access.

Open **Administration → Cron** and generate a new strong web-cron token, then save the settings. Configure the hosting provider's cron service to request:

```text
https://pulse.example.com/cron/cron.php?token=YOUR_CONFIGURED_TOKEN
```

Replace both the hostname and token with your real values.

A successful request returns:

```text
OK
```

The cron URL is a credential. Anyone who has the complete URL also has the token, so:

- do not publish or share it
- restrict access to web-server and hosting logs that may contain the query string
- rotate `PULSE_CRON_TOKEN` if the URL is exposed

The endpoint runs one bounded notification pass. It does not start a normal signed-in Pulse session and does not accept operational settings in the URL.

### Option B: command-line cron

If the server provides normal cron and PHP on the command line, use the command-line worker instead:

```cron
* * * * * cd /path/to/pulse && /usr/bin/php tools/pulse.php notifications:run --limit=25 >/dev/null 2>&1
```

Adjust `/path/to/pulse` and the PHP executable path for your server.

You need only one of the two cron methods. Overlapping runs are designed to be safe, but there is normally no reason to configure both.

## 10. Verify the installation

Before creating a real monitor, check the installation end to end:

1. Open `/health` and verify that Pulse responds.
2. Sign in and open `/health/readiness` to check database and storage readiness.
3. Open **Administration → Mail** and send a successful test email.
4. Call the configured web-cron URL once and verify that it returns `OK`, or run the command-line notification worker once.
5. Create a non-sensitive test contact and monitor.
6. Confirm that the monitor can be checked in and that its next due time is calculated correctly.
7. Rehearse the notification flow with non-sensitive addresses and wording before relying on Pulse for anything consequential.

The [monitor tutorial](MONITOR_TUTORIAL.md) contains example configurations and a rehearsal procedure.

## Upgrading Pulse

Upgrading is simple:

1. Back up the database and `storage/uploads`.
2. Extract the new release locally.
3. If you're working with the project source, run `python3 tools/write_version.py` to update the version number before uploading the PHP files. If you downloaded a release .zip, this isn't necessary.
4. Keep the existing server `.env` and private storage data.
5. Upload the complete new application over the existing Pulse installation.
6. Open Pulse in a browser. Any pending database migrations are applied automatically.
7. Send a test from **Administration → Mail** and verify that the cron job still runs.

Do not import `database/schema.sql` over an existing Pulse database.

A complete-file upload is preferred to copying only files that appear to have changed. This keeps application code, assets, migrations, and language files on the same release.
