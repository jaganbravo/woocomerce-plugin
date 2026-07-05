# Scheduled Email Digests

Store owners get automated HTML email reports without logging in.

## Where to configure

**WordPress Admin → Dataviz AI → Email Digests**

- Create digests with **daily**, **weekly**, or **monthly** frequency.
- Choose **day of week** (weekly), **day of month** (monthly, capped at 28), and **send hour** (server timezone).
- **Recipients**: comma-separated emails; the user who creates the digest is always included.
- Toggle **report sections**: revenue summary, order status breakdown, top products (by category for the period), low stock, top customers, refunds.

## Actions

- **Preview** — HTML preview in the admin (same template as the email).
- **Send Now** — Sends immediately and advances `next_run_at` (same as a successful cron send).

## How sending works

1. A WP-Cron event runs **every 15 minutes** (`dataviz_ai_process_email_digests`).
2. Active digests with `next_run_at <= now` are loaded.
3. `Dataviz_AI_Digest_Generator` pulls data via `Dataviz_AI_Data_Fetcher` (same numbers as chat would use for the period).
4. `Dataviz_AI_Digest_Email_Template` renders HTML; `wp_mail()` sends to each recipient.

## WP-Cron note

WordPress cron is **triggered by visits**. For reliable delivery on low-traffic sites, configure a system cron to hit `wp-cron.php` or use a real cron plugin.

## Troubleshooting: “I didn’t get the email”

1. **Click “Send Now” on a digest** — If sending fails, the admin shows a **red notice** with the underlying error (when WordPress provides one). A generic message usually means PHP `mail()` is not configured (typical on **Docker, Local, MAMP**, etc.).

2. **Use SMTP** — Install **WP Mail SMTP** (or similar) and configure:
   - **Production:** your host’s SMTP or transactional provider (SendGrid, Mailgun, Postmark, Amazon SES).
   - **Development:** [Mailtrap](https://mailtrap.io), [MailHog](https://github.com/mailhog/MailHog), or Gmail SMTP (with an app password).

3. **Spam folder** — Check junk/spam for messages From your site’s **admin email**.

4. **Scheduled digests** — They only run when WP-Cron runs (usually on a page load). Ensure someone/something hits the site, or set up a real cron.

5. **Debug log** — With `WP_DEBUG` and `WP_DEBUG_LOG` enabled, failures are logged as `[Dataviz AI Digest] ...` in `wp-content/debug.log`.

### Error: “Could not instantiate mail function”

That message comes from **PHPMailer** (inside WordPress) when PHP’s **`mail()`** cannot run — **very common in Docker, Local, and some cloud dev containers**. It is **not** a bug in the digest plugin.

**Fix:** Install **WP Mail SMTP** → set mailer to **Other SMTP** (or Gmail / SendGrid) → save → send a test email from the plugin. After that, **Send Now** on a digest should work for `antonyprasan@gmail.com` (or any recipient your SMTP allows).

## Database

Table: `{prefix}dataviz_ai_email_digests` — created on plugin activation (`Dataviz_AI_Email_Digests::create_table()`).
