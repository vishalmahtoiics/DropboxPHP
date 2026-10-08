# DropboxPHP

A CMS and CRM for a Dropbox reseller website, written in plain **PHP 8.1+ and MySQL**. There is no Node, no Composer and no build step: upload the files, open the site, and the installer does the rest.

It rebuilds every feature of the original Next.js / Prisma app (`wroffy-dev/gworkspace-dropbox`). The [feature map](#feature-map) below lists them.

---

## Requirements

| | |
|---|---|
| PHP | 8.1 or newer (8.3 recommended). The extensions you need are `pdo_mysql`, `sodium`, `mbstring`, `fileinfo`, `curl`, `zip`, `dom` and `openssl`. The installer checks them. All are standard on cPanel / Plesk hosts. |
| Database | MySQL 8+ or MariaDB 10.6+ (InnoDB, utf8mb4) |
| Web server | Apache with `mod_rewrite` (cPanel), or nginx + PHP-FPM |

## Installation

There are no configuration files to edit and no terminal commands to run. The site installs itself from the browser.

1. **Create a database.** In your hosting panel (cPanel → **MySQL Databases**), create a database and a user, and add the user to the database with **ALL PRIVILEGES**.
2. **Upload the files.**
   - **Best:** point the domain's document root at the `public/` folder.
   - **If you can't change the document root:** upload everything into `public_html`. The `.htaccess` files send requests into `public/` and refuse access to `app/`, `storage/` and the other private folders.
3. **Open the website.** It goes straight to **`/install`**:
   - checks the server (PHP version, extensions, writable folders);
   - asks for the website address, the database details and your admin account;
   - lets you choose the secret admin sign-in address and the timezone, and whether to add demo content.
4. **Click Install.** The installer creates the tables and your admin account. It also generates the encryption key and the cron token itself. **Copy the encryption key it shows** into a password manager.
5. **Sign in** at your sign-in address, scan the QR code with an authenticator app (MFA is mandatory) and save the recovery codes.
6. **Set up the cron job.** Admin → Website settings → **System** shows the exact command and URL to copy.

Once installed, `/install` no longer exists.

The installer saves the database login and the key in `storage/installed.php`. Nobody edits that file: it is git-ignored, has 0600 permissions, and is never served over the web.

### Changing settings later

Everything is in the admin panel:

| What | Where |
|---|---|
| Website address, admin sign-in address, timezone | Website settings → **System** |
| Cloudflare / load-balancer IPs (so rate limits see the real visitor IP) | Website settings → **System** |
| The encryption key (Super Admin, after re-entering the password) | Website settings → **System** |
| Database updates after uploading a new version | Website settings → **System** → Apply updates |
| SMTP email | Website settings → **Email** |
| Amazon S3 / Cloudflare R2 for uploads | Website settings → **Media storage** |
| Brand, header, footer, maintenance mode | Website settings |

### Moving to a new server

1. Copy the files and import the database.
2. Delete `storage/installed.php` if it was copied.
3. Open the site. The installer sees that the database already holds a site and asks for the **encryption key** instead of a new admin account. That key is what MFA secrets and stored passwords are locked with, so the installer refuses any other key.

### nginx + PHP-FPM

`deploy/nginx.conf` is a ready-made server block. Only `public/index.php` can be executed, and dotfiles are never served.

### Local development

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

Open http://127.0.0.1:8000. You get the same installer. On the built-in server, errors are shown in full.

## Cron

The cron run does three things:

- publishes scheduled pages, posts and products;
- cleans up expired sessions and rate-limit rows;
- runs scheduled backups.

Run it every 5 minutes. Admin → Website settings → System shows both options, filled in for your server:

```cron
*/5 * * * * cd /path/to/dropboxphp && php bin/console cron > /dev/null 2>&1
```

If the host has no CLI cron, call the HTTP address instead. The installer creates its secret token:

```
https://www.example.com/cron/run?token=<token shown in Admin → System>
```

## Console (optional)

Nothing needs the terminal. These commands are there if you have SSH access, and they work once the site is installed:

| Command | Purpose |
|---|---|
| `migrate`, `migrate:status` | Apply or list database updates (same as the button in Admin → System) |
| `db:seed [--no-demo]` | Re-create markets, email templates and demo content |
| `user:create` | Create a Super Admin |
| `user:reset-mfa [email]` | Reset a locked-out user's MFA |
| `cron` | Run the scheduled jobs once |
| `status` | Installation and database check |

## Tests

```bash
php tests/run.php unit    # no database needed
php tests/run.php         # + integration tests against "<site database>_test" (wiped every run; install the site first)
```

CI (`.github/workflows/ci.yml`) lints every file and runs both suites on PHP 8.1 and 8.3 against MySQL 8.4.

---

## Security

| Area | What is done |
|---|---|
| SQL | Every query goes through PDO with native prepared statements (emulation off). Identifiers are checked against an allow-list. |
| XSS | All output is escaped with `e()`. Rich text goes through an allow-list DOM sanitiser, and URLs are scheme-checked. CSP uses per-request nonces: strict in the admin, `strict-dynamic` on the public site. |
| CSRF | Every admin POST needs a synchroniser token. Public forms use signed HMAC tokens with a minimum fill time and a maximum age, plus a honeypot. |
| Sessions | Cookies are HttpOnly, SameSite=Lax, Secure on HTTPS and in strict mode. Session IDs are regenerated at login and at MFA. Sessions are stored server-side, so they can be revoked (log out everywhere). |
| Login | The sign-in address is secret and chosen at install, and `/admin` returns 404 when you're not signed in. Passwords use Argon2id. Sign-in timing is constant whether or not the user exists. Login attempts are rate-limited per account and per IP. |
| MFA | TOTP (RFC 6238) is mandatory for every staff user, with replay protection and hashed one-time recovery codes. |
| Access control | Roles have a rank hierarchy and fine-grained permissions. Nobody can grant a role above their own. |
| Secrets | Secrets saved in Settings (e.g. the SMTP password) are encrypted at rest with XChaCha20-Poly1305 (libsodium). Keys are derived from the site's encryption key, which the installer generates. |
| Privacy | IP addresses are stored only as keyed HMAC hashes. Consent records can be withdrawn from the CRM. |
| Uploads | File types are checked by their content (magic bytes), not their names. SVGs are scanned for scripts. Files are stored outside the web root and served with `nosniff` and a sandbox CSP. |
| Exports | CSV exports are guarded against formula injection. |
| Headers | HSTS (on HTTPS), X-Frame-Options, nosniff, Referrer-Policy and Permissions-Policy are sent. |
| Audit | Every admin change is written to the audit log, with secrets scrubbed. |

Error details are never shown on a live site. **Keep a copy of the encryption key** (shown by the installer, and in Admin → System). Without it, MFA secrets and stored passwords can't be read after a move to another server.

---

## Feature map

| Original (Next.js) | Here |
|---|---|
| Multi-country markets (`/`, `/ae`, …) with hreflang | Admin → Countries; `Countries::resolve`; alternates in `<head>` |
| Page builder (25 block types) with a design panel per section (spacing, background, colours, per-breakpoint) | Admin → Pages → Sections; `app/Cms/Blocks.php`, `Design.php`, `views/blocks/*` |
| Page categories, scheduled publishing, preview, duplicate, copy to another market | Admin → Pages |
| Cities + City Page Generator | Admin → Cities |
| Products with per-market pricing, categories, brands, comparison tables | Admin → Products |
| Blog with categories, tags, authors and scheduling | Admin → Blog |
| Form builder (fields, validation, captcha, notifications, lead creation) | Admin → Forms |
| Lead capture with first/last-touch UTM attribution and consent records | `LeadCapture`, `Attribution` services |
| CRM: leads, notes, pipeline kanban, customers, reports, CSV export | Admin → Leads / Pipeline / Customers / Reports |
| Popups and lead magnets (gated downloads) | Admin → Popups / Lead magnets |
| Navigation menus (header, footer, legal, mobile) | Admin → Navigation |
| SEO: meta, JSON-LD, sitemap, robots, SEO/AEO/GEO scoring | Admin → SEO; `/sitemap.xml`, `/robots.txt` |
| Redirects, 404 log, URL registry | Admin → Redirects. Old slugs redirect automatically when a slug changes, and loops are refused. |
| Marketing tags (GA4, GTM, Meta Pixel, …) behind a consent banner | Admin → Marketing |
| Media library: local disk, Amazon S3 or Cloudflare R2 | Admin → Media; Website settings → Media storage (SigV4 signing, no SDK) |
| Email (SMTP) with editable templates | Settings → Email; built-in SMTP client |
| Staff, roles and permissions; mandatory MFA | Admin → Staff / Roles |
| Recycle bin, audit log | Admin → Trash / Audit |
| Backups: database + media ZIP, scheduled backups, restore with a safety backup first | Admin → Backups |
| Maintenance mode, health checks (`/health`, `/ready`) | Settings → Website |

## Layout

```
app/Core       framework: router, DB, sessions, auth, CSRF, crypto, sanitiser, TOTP…
app/Cms        block registry, design panel, section renderer, icons
app/Services   business logic (settings, leads, SEO, storage, backups…)
app/Http       controllers (Admin, Site, Auth) and the form helpers
database       migrations and the seeder
views          PHP templates (admin, site, blocks, layouts)
public         web root: index.php, assets
storage        uploads, backups, logs, cache, installed.php (never web-accessible)
deploy         sample nginx config
tests          dependency-free test runner and suites
```
