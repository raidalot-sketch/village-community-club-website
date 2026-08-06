# The Village Community Club — Website & Admin System

A complete, self-hosted website for a small village community club, built with
plain PHP, MySQL/MariaDB, semantic HTML5, modern CSS3 and vanilla JavaScript.
No paid services, no Node.js requirement, no build step — designed to run on
ordinary shared hosting with cPanel.

This document explains how to install, configure, maintain and safely update
the site. See also [`documentation/PRELAUNCH-CHECKLIST.md`](documentation/PRELAUNCH-CHECKLIST.md)
for a checklist of placeholder content to replace before going live.

---

## 1. Requirements

- PHP **8.0 or later**, with these extensions (all standard on cPanel hosts):
  `pdo_mysql`, `gd`, `fileinfo`, `json`, `mbstring`, `openssl`
- MySQL 5.7+ or MariaDB 10.3+
- Apache or LiteSpeed with `mod_rewrite` and `mod_headers` (standard on cPanel)
- No Node.js, Composer, or build tools are required to run the site

## 2. How to Create the Database

In cPanel:

1. Go to **MySQL® Databases**.
2. Under "Create New Database", enter a name (e.g. `villageclub`) and click **Create Database**. cPanel will usually prefix it, e.g. `youruser_villageclub`.
3. Under "MySQL Users", create a new user with a strong password.
4. Under "Add User to Database", add that user to the database you created and grant **ALL PRIVILEGES**.
5. Note down the database host (usually `localhost`), database name, username and password — you'll need these in step 4.

## 3. How to Import the SQL File

You do **not** need to import `database/install.sql` manually — the installer
(`install.php`) does this for you automatically once you give it your
database details in step 4. If you ever need to do it manually (e.g. via
phpMyAdmin's "Import" tab or the `mysql` command line), the file is at
`database/install.sql`.

## 4. How to Enter Database Credentials / Run the Installer

1. Upload the entire project to your hosting (see section 9).
2. Visit `https://yourdomain.com/install.php` in a browser.
3. **Step 1** checks your server meets the requirements.
4. **Step 2** asks for your database host/name/username/password (from step 2 above) plus your website's URL, club name and village name. This writes `config/config.php` for you and imports the database schema.
5. **Step 3** creates your first administrator account (see section 5).
6. **Step 4** confirms installation is complete.

If you need to configure the database by hand instead of using the installer,
copy `config/config.sample.php` to `config/config.php` and fill in your own
values — see that file for every available setting.

## 5. How to Create the First Administrator Account

This happens automatically in **Step 3** of the installer — you'll be asked
for a full name, username, email address and password (minimum 10
characters). No default or sample password ships with this project.

To create additional admin or editor accounts later, log in and go to
**Admin > Users** (Administrator role only).

## 6. How to Configure the Website URL

Set this once, during installation (Step 2). If you ever move the site to a
new domain, edit `SITE_URL` in `config/config.php` to match exactly (no
trailing slash), e.g.:

```php
define('SITE_URL', 'https://www.yourclub.org.uk');
```

## 7. How to Configure Email

Edit these values in `config/config.php`:

- `MAIL_METHOD` — `'mail'` uses PHP's built-in `mail()` function, which works
  out of the box on most cPanel hosts. `'smtp'` uses the SMTP settings below
  instead (recommended if `mail()` messages aren't arriving, or are marked as
  spam).
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` — the "From" shown on outgoing email.
- `MAIL_TO_ADDRESS` — where contact form messages are sent.
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION`
  — only needed if `MAIL_METHOD` is `'smtp'`. Ask your email/hosting provider
  for these details (for cPanel email accounts, they're usually on the
  "Email Accounts > Connect Devices" page).

Email credentials live only in `config/config.php`, which is excluded from
version control and blocked from direct web access — never put them in a
public-facing file.

## 8. Which Folders Require Write Permissions

The web server needs to be able to write to:

- `/config` (only during installation, to create `config.php`)
- `/uploads` and all its subfolders (`news`, `events`, `newsletters`,
  `gallery`, `committee`, `pages`) — for file uploads
- `/logs` (created automatically on first run, for error logging)

On most cPanel hosts, folders are writable by default for the account that
owns them (i.e. your account, via FTP/File Manager), so no extra `chmod` is
usually needed. If you do see "permission denied" errors, set these folders
to `755` (or ask your host).

## 9. How to Upload the Website Through cPanel or FTP

**Option A — cPanel File Manager:**
1. Zip the project folder locally.
2. In cPanel, open **File Manager**, navigate to `public_html` (or a
   subdomain's folder), and upload the zip.
3. Right-click the uploaded zip and choose **Extract**.

**Option B — FTP/SFTP:**
1. Connect with an FTP client (e.g. FileZilla) using the credentials from
   cPanel's **FTP Accounts** page.
2. Upload the entire project folder's contents into `public_html` (or your
   subdomain's document root).

Either way, once uploaded, visit `install.php` in your browser to finish
setup (section 4).

**Using a subdomain:** if you're testing this on a subdomain (e.g.
`villageclub.yourdomain.co.za`), create the subdomain in cPanel first (**Domains
> Subdomains**), then upload the site into that subdomain's document root
instead of `public_html`.

## 10. How to Log In to the Admin Area

Go to `https://yourdomain.com/admin/login.php` and sign in with the
administrator account created during installation. Forgotten your password?
Use the "Forgotten your password?" link — this requires email to be working
(see section 7).

## 11. How to Change the Club's Name, Colours and Contact Details

- **Club name, village name, tagline, hero text, opening hours, address,
  social links, contact details:** Admin > Settings (Administrator role
  only).
- **Page text** (About, Membership, Facilities, Committee intro, Privacy,
  Cookies, Accessibility): Admin > Page Content.
- **Colours, fonts, spacing:** edit the CSS variables at the top of
  `assets/css/style.css` — every colour and font on the site is controlled
  from that one block, so re-branding doesn't require touching any other
  file.

## 12. How to Create Backups

At minimum, back up two things regularly:

1. **The database** — in cPanel, use **phpMyAdmin > Export** (choose "Quick"
   export, SQL format), or **Backup Wizard** if available. Many hosts also
   offer automatic nightly backups — check with your provider.
2. **The `/uploads` folder** — this contains every image, PDF and photo
   uploaded through the admin area, and is not part of the database. Download
   it periodically via FTP, or use cPanel's backup tools.

`config/config.php` is not stored in version control (it contains your
database password) — keep a secure personal copy of it too.

## 13. How to Update the Website Safely

1. **Always back up the database and `/uploads` folder first** (section 12).
2. Never overwrite `config/config.php` when updating files — it's not part of
   the codebase and contains your live settings.
3. Test changes on a staging copy or subdomain first if possible, before
   updating the live site.
4. If you update the database schema in future, always run new SQL changes
   against a backup copy first.

## 14. How to Secure or Remove the Installation Script

Once installation is complete, `install.php` writes `config/installed.lock`
and refuses to run again. For extra safety, **delete `install.php` from the
server entirely** once you're happy everything works — it's not needed for
day-to-day operation of the site.

## 15. Troubleshooting

| Problem | Likely cause / fix |
|---|---|
| "Database connection failed" | Check `config/config.php` has the correct host/name/user/password. Confirm the database user has privileges on that database in cPanel. |
| Blank white page | A PHP error occurred. Check `/logs/php-error.log`. Set `APP_ENV` to `'development'` temporarily in `config/config.php` to see the full error (switch it back to `'production'` afterwards). |
| Uploaded images don't appear | Check `/uploads` and its subfolders are writable. Confirm the file is a real JPG/PNG/WebP under the configured size limit. |
| Contact form emails not arriving | Check spam folders first. Try switching `MAIL_METHOD` to `'smtp'` with your host's SMTP details (section 7) — many hosts silently drop `mail()` messages that look like spam. |
| "This website has already been installed" | `config/installed.lock` exists. Delete it if you genuinely need to reinstall (this does **not** delete your database). |
| Locked out after failed logins | Wait 15 minutes — failed login attempts are rate-limited per account and per IP address for security. |
| Styling looks broken | Confirm `assets/css/style.css` and `assets/css/admin.css` uploaded correctly and are reachable (check for a 404 in your browser's network tab). |

---

## Project Structure

```
/                       Public-facing PHP pages (index.php, about.php, ...)
/admin/                 Admin dashboard and CMS (login-protected)
/includes/              Shared PHP: bootstrap, database, auth, helpers, templates
/config/                config.php (database + site settings — not in git)
/assets/css/            style.css (public site) and admin.css (admin area)
/assets/js/             main.js (public site) and admin.js (admin area)
/assets/vendor/quill/   Locally-hosted rich text editor (no external CDN)
/assets/images/placeholders/  Clearly labelled placeholder images
/uploads/               User-uploaded images, PDFs and photos (by category)
/database/install.sql   Full schema + starter/demo content
/documentation/         This project's extra documentation
```

## Roles

- **Administrator** — full access, including Settings, Users, and the Audit Log.
- **Editor** — can manage news, events, newsletters, gallery, committee and
  page content, but cannot access Settings, Users, or delete administrator
  accounts.

## Security Notes

- Passwords are hashed with PHP's `password_hash()` (bcrypt) and never stored
  in plain text.
- All database queries use prepared statements (PDO) — never raw string
  concatenation.
- Every admin form is protected against CSRF with a per-session token.
- Rich text content (news articles, page content, event descriptions) is
  sanitised server-side against a strict allow-list of tags/attributes before
  it is stored.
- Uploaded files are validated by their real content (not filename or
  browser-supplied type), renamed to random filenames, and stored in folders
  where PHP execution is disabled.
- Login attempts are rate-limited (5 attempts per 15 minutes, per account and
  per IP address).
- Admin sessions automatically expire after 20 minutes of inactivity
  (configurable in `config/config.php`).
- "Remember me" login was deliberately **not** implemented, to keep the
  session/cookie security model simple and safe for a small-hosting
  environment — admins simply log in each session.
