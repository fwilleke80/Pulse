# Pulse

Pulse is a small personal web application designed as an **emergency notification and heartbeat system**.

Its purpose is to notify trusted contacts if the user becomes unreachable or fails to confirm that they are safe within a configured time period.

The project is intentionally lightweight and designed to run on inexpensive shared hosting without external frameworks.

## Original idea, as worded by Frank

It should be a kind of emergency notification service made to notify my family & loved ones in case something happens to me.

I can log in, and set up emergency contacts (Name & eMail). I can also set up grace periods. This means: If I set up a grace period of e.g. 1 month, the web app will send me an email once a month. The email contains a link that I need to click on to indicate I'm still alive. If I fail to click the link within a certain period of time after the mail has been sent (e.g. a week), the mail to me will be sent again.

If, after a number of times, I still didn't click the link, the emergency contacts assigned to this grace period will be notified. Each emergency contact gets a personalised email for a grace period (meaning: I can write a personalised message for each emergency contact assigned to this grace period). The notification mail contains a link that the emergency contact person can click on. The link will open a document that I have written/uploaded for this emergency contact in this grace period (e.g. a personal goopdbye letter, my testament, et cetera).

Setting up different grace periods will allow me to e.g. use a longer period for when I die, and a shorter period for if I disappear during a vacation, or so.

---

## Features

Pulse provides the following core functionality:

- Periodic **heartbeat confirmations**
- Configurable **grace periods**
- Management of **emergency contacts**
- Escalation if confirmations fail
- Delivery of **personal messages or documents** to contacts
- Multi-language interface (currently English and German)

---

## Concept

Pulse regularly sends the user a confirmation email containing a link.

Clicking the link confirms that the user is safe.

If the confirmation is not received within the configured time window:

1. Reminder emails are sent
2. After the configured number of reminders
3. Emergency contacts are notified

Contacts may receive:

- personal messages
- instructions
- documents
- final letters

Typical use cases include:

- travel safety check-ins
- long-term inactivity alerts
- digital legacy notifications

---

## Technology Stack

Pulse intentionally uses a minimal stack:

| Component | Technology |
| -------- | -------- |
| Language | PHP 8+ |
| Database | MySQL / MariaDB |
| Web server | Apache |
| Frontend | HTML + CSS |
| Routing | Custom lightweight router |

No external frameworks are required.

---

## Project Structure

```txt
/pulse
  /app
    Application code
  /config
    Configuration
  /database
    SQL schema and migrations
  /public
    Web entry point
  /storage
    Runtime data
  /bootstrap.php
    Application initialization
```

Only the `/public` directory is exposed to the web server.

---

## Installation

1. Clone or download the repository.
2. Create a database and import the schema:  
  `/database/schema.sql`
3. Configure the application:  
  `/config/app.php`
  `/config/database.php`
4. Point your web server document root to:  
  `/public`
5. Ensure the following directories are writable:  
  `/storage`
  `/storage/logs`
  `/storage/uploads`
  `/storage/tmp`

---

## Development

Enable debug mode in: `config/app.php`.

`'debug' => true`

This enables detailed error output and debugging pages.

---

## Security Notes

For production deployments:

- Disable debug mode
- Remove development helper scripts
- Ensure `/public` is the only web-accessible directory
- Use HTTPS
- Use strong passwords

---

## License

This project is licensed under the MIT License.  
See the `LICENSE` file for details.
