# DropboxPHP

A CMS and CRM for a Dropbox reseller website, written in plain **PHP 8.1+ and MySQL**. There is no Node, no Composer and no build step: upload the files, point the web root at `public/`, and run the migrations.

It rebuilds every feature of the original Next.js / Prisma app (`wroffy-dev/gworkspace-dropbox`). The [feature map](#feature-map) below lists them.

---

## Requirements

| | |
|---|---|
| PHP | 8.1 or newer (8.3 recommended). The extensions you need are `pdo_mysql`, `sodium`, `mbstring`, `fileinfo`, `curl`, `zip` and `dom`. All are standard on cPanel / Plesk hosts. |
| Database | MySQL 8+ or MariaDB 10.6+ (InnoDB, utf8mb4) |
| Web server | Apache with `mod_rewrite`, or nginx + PHP-FPM |

## Quick start

```bash
cp .env.example .env
php bin/console key:generate          # writes APP_KEY into .env
# edit .env: APP_URL, DB_*, SEED_ADMIN_EMAIL, SEED_ADMIN_PASSWORD
php bin/console migrate
php bin/console db:seed               # India + UAE markets, admin user, email templates, demo content
php -S 127.0.0.1:8000 -t public public/index.php   # local development only
```

After seeding, open `APP_URL` + `LOGIN_PATH`. The default is `/auth-control-panel/admin`.

1. Sign in.
2. Scan the QR code with an authenticator app. MFA is mandatory.
3. Save the recovery codes.

When that's done, remove `SEED_ADMIN_PASSWORD` from `.env`.

## Deployment

### Shared hosting (cPanel / Apache)

Choose one of these:

- **Best:** set the domain's document root to the `public/` folder.
- **If you can't change the document root:** upload the whole project into the web root. The root `.htaccess` sends every request into `public/` and blocks `.env`, `app/`, `storage/` and the rest.

Then:

1. Create the MySQL database and user in cPanel, and fill in `DB_*` in `.env`.
2. Run `php bin/console migrate` and `php bin/console db:seed`, either in cPanel's Terminal or over SSH.
3. Make `storage/` writable by PHP (`chmod -R 775 storage`).
4. Set up the cron job (see below).

### nginx + PHP-FPM

`deploy/nginx.conf` is a ready-made server block. Only `public/index.php` can be executed, and dotfiles are never served.

### Docker

```bash
cp .env.example .env    # set DB_PASSWORD, APP_KEY, SEED_ADMIN_*
docker compose up -d --build
docker compose exec app php bin/console migrate
docker compose exec app php bin/console db:seed
# → http://localhost:8080
```

The stack is PHP-FPM, nginx, MySQL 8.4 and a cron worker. It contains no Node.

### Behind Cloudflare or a load balancer

Put the proxy IPs in `TRUSTED_PROXIES`. Rate limits and audit logs then see the real client IP.

## Cron

The cron run does three things:

- publishes scheduled pages, posts and products;
- cleans up expired sessions and rate-limit rows;
- runs scheduled backups.

Run it every 5 minutes, using either option:

```cron
*/5 * * * * cd /path/to/dropboxphp && php bin/console cron > /dev/null 2>&1
```

If the host has no CLI cron, call the HTTP endpoint instead. It needs `CRON_SECRET` in `.env`, at least 24 characters:

```
https://www.example.com/cron/run?token=YOUR_CRON_SECRET
```

## Console

| Command | Purpose |
|---|---|
| `key:generate [--raw]` | Create APP_KEY (or print a random secret) |
| `migrate`, `migrate:status` | Apply or list database migrations |
| `db:seed` | Seed markets, the first admin, templates and demo content |
| `user:create` | Create a Super Admin interactively |
| `user:reset-mfa [email]` | Reset a locked-out user's MFA |
| `cron` | Run the scheduled jobs once |
| `status` | Environment and configuration check |

## Tests

```bash
php tests/run.php unit    # no database needed
php tests/run.php         # + integration tests against "<DB_DATABASE>_test" (it is wiped every run)
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
| Login | The login URL is hidden (`LOGIN_PATH`), and `/admin` returns 404 when you're not signed in. Passwords use Argon2id. Sign-in timing is constant whether or not the user exists. Login attempts are rate-limited per account and per IP. |
| MFA | TOTP (RFC 6238) is mandatory for every staff user, with replay protection and hashed one-time recovery codes. |
| Access control | Roles have a rank hierarchy and fine-grained permissions. Nobody can grant a role above their own. |
| Secrets | Secrets saved in Settings (e.g. the SMTP password) are encrypted at rest with XChaCha20-Poly1305 (libsodium). Keys are derived from `APP_KEY`. |
| Privacy | IP addresses are stored only as keyed HMAC hashes. Consent records can be withdrawn from the CRM. |
| Uploads | File types are checked by their content (magic bytes), not their names. SVGs are scanned for scripts. Files are stored outside the web root and served with `nosniff` and a sandbox CSP. |
| Exports | CSV exports are guarded against formula injection. |
| Headers | HSTS (on HTTPS), X-Frame-Options, nosniff, Referrer-Policy and Permissions-Policy are sent. |
| Audit | Every admin change is written to the audit log, with secrets scrubbed. |

Keep `APP_DEBUG=false` in production. **Back up `APP_KEY`**: without it, encrypted settings and existing sessions can't be read.

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
| Media library: local disk, Amazon S3 or Cloudflare R2 | Admin → Media; `STORAGE_DRIVER` + `S3_*` in `.env` (SigV4 signing, no SDK) |
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
storage        uploads, backups, logs, cache (not web-accessible)
deploy         nginx and Docker configs
tests          dependency-free test runner and suites
```
