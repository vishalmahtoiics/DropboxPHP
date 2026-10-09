# Brand CMS & CRM

A white-label CMS and CRM for a reseller website, written in plain **PHP 8.1+ and MySQL**. The brand is a setting, not part of the code. There is no Node, no Composer and no build step: upload the files, open the site, and the installer does the rest.

It rebuilds every feature of the original Next.js / Prisma app in PHP. The [feature map](#feature-map) below lists them.

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

## Brand name

The brand is set in one place: **Admin → Website settings → General → Brand name** (it starts as "Brand Name"). It appears everywhere on its own:
- as the logo text, or the first letter of the name when no logo is uploaded;
- in page titles and SEO;
- in the footer, copyright and emails;
- in the authenticator app;
- in the admin and on the sign-in screen.

**`{brand}` in any text** — page sections, builder widgets, products, blog posts, SEO settings, email templates, menus — shows the brand name. The default texts already use it, for example `%s | {brand}` for page titles. Rename the brand in settings and every page, title and email follows on the next page load. What is stored keeps `{brand}`, so it keeps working after the next rename too.

In the footer copyright, `{site}` is the market's company name (Admin → Countries), which itself can contain `{brand}`, e.g. `{brand} India`.

## Visual page builder (drag and drop)

Pages and product pages can be designed visually, in the style of Elementor:

- **Containers** are flex boxes:
  - row or column, gap, justify and align, wrap;
  - width, minimum height, boxed or full width;
  - background (colour, gradient, image, overlay), border, radius, shadow.
- Containers nest inside each other to make columns. The structure picker sets up 2, 3 or 4 columns, or uneven splits, in one click.
- **Widgets** (over 60):
  - **Basic:** heading, text editor, image, button, icon, icon box, image box, icon list, spacer, divider, video.
  - **General:** image slider (autoplay, arrows, dots, swipe; slides without an image are skipped), counter, testimonial, accordion/FAQ (with FAQ structured data), tabs, social icons, notice.
  - **Site:** form, breadcrumbs, site logo, menu, country switcher, copyright, contact info.
  - **Code:** custom HTML (permission-gated).
  - **Product (product pages and templates):** title, price with the monthly/annual switch, image, gallery, short description, description, features, benefits, specifications, button, enquiry form, related products, breadcrumbs.
  - **Ready-made sections:** every block of the classic editor.
- **Every element has three tabs:**
  - **Content:** what it shows.
  - **Style:** colours, typography and sizes.
  - **Advanced:** margin and padding, width, align-self, order, z-index, CSS id and classes, entrance animation, hide on desktop/tablet/mobile.
- **Responsive editing:** switch the canvas between desktop, tablet (≤1024px) and mobile (≤767px). Settings marked with the device icon are stored separately for each size.
- **Editing tools:**
  - drag widgets from the panel onto the page, and drag elements by their handle to move them;
  - the right-click menu: duplicate, copy, paste, **paste style** (another element's look, not its content), delete, select parent, save as template;
  - **copy and paste across pages**, like Elementor: copy a container or widget on one page, open another page (or a product or template) and paste it with right-click → Paste or Ctrl+V. Right-clicking empty space also offers Paste. The clipboard lives in this browser, so it works between tabs too;
  - sections of the **classic editor** copy the same way: ⋯ → *Copy*, then *Paste section* on any page. A classic section pastes into the builder, and anything from the builder made of ready-made sections pastes into the classic editor;
  - a navigator tree that can also be reordered by dragging;
  - undo/redo and keyboard shortcuts (Ctrl+Z / Ctrl+Shift+Z, Ctrl+C / V / D, Delete, Ctrl+S).
- **Drafts and publishing:**
  - **Save draft** keeps changes private. Previews show the draft, and drafts are also saved automatically every minute.
  - **Publish / Update** makes the layout live and keeps a revision. The last 30 revisions can be loaded back.
  - Publishing a live page needs the *Publish pages* permission.
- **Templates** (Admin → Builder templates):
  - Save any container, or a whole page, and insert it anywhere.
  - A **product template** designs product pages. Mark one as the default and every product that uses templates follows it. Each product can also pick another template, keep the classic page, or get its own layout (Product → *Product page design*).

- **Header and footer builder** (Admin → Builder templates → *Site header* / *Site footer*):
  - Design the site header and footer with the same editor.
  - Site widgets: logo, menu, country switcher, copyright and contact info. The menu shows the market's menu from Admin → Navigation; on phones it becomes a menu button, and the *Columns* layout suits footers.
  - Containers can be made *sticky* (Advanced → Position).
  - A new header or footer only goes live when you click **Make active**. Turn it off to return to the built-in one. Pages can still hide the header or footer individually.
- **Custom code** (needs the *Add custom HTML and CSS in the visual builder* permission; Super Admin has it, and other roles can get it under Roles):
  - The **Custom HTML** widget, for embeds such as maps or booking calendars, and scripts.
  - **Custom CSS** on every element (Advanced → Custom CSS, where `selector` means that element) and for the whole page (page settings).
  - Scripts run on the live page with the page's CSP nonce, but never inside the editor.
  - CSS is cleaned so it can't close its `<style>` tag or load outside stylesheets.
  - Staff without the permission can't add or change code. Code that is already in a layout stays exactly as it was when they save.
- **Import and export (.json files):**
  - In the builder, the **Import / export** button (folder icon in the top bar) downloads the layout as a `.json` file, unsaved changes included. Right-click a container → **Export as file** downloads just that section.
  - **Import** in the same dialog reads a `.json` file and puts it at the end of the layout or in place of everything on it. Nothing goes live until you publish, and Undo takes it back.
  - **Admin → Builder templates → Import a template** turns a file into a new template (page, section, product, header or footer). It never becomes active by itself. Every template row also has a download button.
  - Files move layouts between pages, products and sites without touching the database. Forms, menus, products and media files are not inside the file: the layout only refers to them (by form, menu location or address), so they must exist on the site the file is imported into.
  - An imported file is treated like anything else the editor sends: it is cleaned, gets fresh element ids, and loses its custom HTML/CSS when your role cannot add code.
- **Import a whole site** (open `/admin/site-import` while signed in — it is deliberately not in the admin menu, and only administrators with *Manage website settings*, *Create pages* and *Upload media* can use it):
  - Upload a *site package* (.zip): pages built in the visual builder, their images, forms, the header and footer, menus and redirects.
  - New pages are created as **drafts** at the addresses in the package. A page that already exists keeps its live version and gets the import as its builder draft, or is skipped.
  - Images go into the Media library (a folder named after the package) through the normal upload checks; the pages point at their new addresses. Images in a year/month folder keep it and their name (`media/2018/11/Logo.png` → `/media/2018/11/logo.png`) when that address is free, and importing the same file again reuses it. A package can list each image's addresses on the old site (`media_sources` in the manifest); with redirects on, those old addresses redirect (301) to the new ones, and the report lists every image's new address.
  - Forms are created when their key is free; the header and footer arrive as inactive templates; menus are replaced only when you tick the option; redirects are added unless they already exist.
  - **Replace everything** (a toggle at the top) makes the package the live site in one go: pages at the same address are overwritten and published (older versions stay in the builder history), new pages are published, forms with the same key are rebuilt, templates with the same name are overwritten and the header and footer become active, and the menus are replaced. It also needs *Publish pages* and *Manage navigation*.
  - Each part needs its own permission (pages and media to import at all; forms, navigation, SEO and code for those parts). The package format is described in `App\Services\SiteImport`.
- **Changing addresses:**
  - The builder's ⚙ **settings** dialog changes the page or product title, its address (URL), SEO title and description, and whether the page shows the header and footer.
  - When a live page's address changes, the old address keeps working with a permanent redirect.
  - In the **Media library**, *File address* gives a file a readable URL (e.g. `/media/2026/10/business-plan-brochure.png`). The file is moved, and every page, layout, template and setting that used the old address is updated. For files stored on this server, the old address redirects to the new one.

**Opening a page in the builder:** use **Edit with Builder** on the page. Its existing sections are copied into the builder as ready-made sections, so nothing is lost. **Switch back to sections** returns to the classic editor, and the builder layout is kept.

**How it stays exact and safe:**
- The canvas is the real page in an iframe.
- Every change is rendered by the same PHP code the public site uses, so what you see is what visitors get.
- The server cleans everything the editor sends before it is stored or rendered:
  - unknown widgets and settings are dropped;
  - every value is checked against its control: numbers, units from a fixed list, valid colours and links;
  - rich text goes through the HTML sanitiser;
  - the size and depth of a layout are capped.
- CSS is generated per element (`.el-<id>`) from these checked values only. The only free-form HTML and CSS are the custom-code fields, which need their own permission.

## Cron

The cron run does three things:

- publishes scheduled pages, posts and products;
- cleans up expired sessions and rate-limit rows;
- runs scheduled backups.

Run it every 5 minutes. Admin → Website settings → System shows both options, filled in for your server:

```cron
*/5 * * * * cd /path/to/site && php bin/console cron > /dev/null 2>&1
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
| Visual drag-and-drop builder for pages and product pages, with product templates | Edit with Builder; `app/Builder/*`, `public/assets/js/builder*.js` — see above |
| — | **Bulk actions** on every list: tick rows (select all, Shift-click a range) and apply one action — pages/products/articles: publish (pages: builder drafts go live too), draft, archive, recycle bin; leads and customers: status, owner; forms, popups, redirects: on/off, delete; media: move to folder, delete (files in use are kept unless you choose otherwise); recycle bin: restore or delete forever; builder templates: delete. Each action checks the same permission as doing it one by one, and the live homepage is never taken offline in bulk. `Form::bulkBar()`, `bulk()` in each controller |
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
app/Builder    visual builder: elements and controls, layout cleaning, CSS, renderer, storage
app/Services   business logic (settings, leads, SEO, storage, backups…)
app/Http       controllers (Admin, Site, Auth) and the form helpers
database       migrations and the seeder
views          PHP templates (admin, site, blocks, layouts)
public         web root: index.php, assets
storage        uploads, backups, logs, cache, installed.php (never web-accessible)
deploy         sample nginx config
tests          dependency-free test runner and suites
```
