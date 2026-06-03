# EaseLogs Community Edition — Installation Guide

This guide walks you through installing and using **EaseLogs Community Edition** on your own computer. You do not need to be a Laravel developer; follow the steps in order and use the troubleshooting section if something goes wrong.

For platform-specific notes (Windows PowerShell paths, firewall, LAN access), see also [COMMUNITY_EDITION.md](COMMUNITY_EDITION.md).

---

## 1. Introduction

### What is EaseLogs Community Edition?

EaseLogs Community Edition is a **self-hosted** web application for cataloging physical artwork: titles, dates, type, medium, dimensions, notes, photos, and completion status. Your data stays on **your** machine in a SQLite database and local file storage—not on a vendor cloud.

### Who is it for?

- Hobbyist and professional artists who want a private inventory
- Self-hosters comfortable running a few terminal commands
- Linux and Windows users on a home or studio network
- People with **limited Laravel experience** (this guide explains each step)

### Single-owner design

Each install supports **one owner account**. There is no public sign-up page. The first person to complete setup owns the inventory. This keeps Community Edition simple and appropriate for a single studio or home computer.

### Self-hosted model

You install the application, run it locally (or on your LAN), and back up two things yourself: the database file and uploaded photos. See [COMMUNITY_BACKUP.md](COMMUNITY_BACKUP.md) before upgrades or destructive commands.

**Advanced deployment** (optional local nginx at `easelogs.local`) is documented in [LOCAL_INTRANET_DEPLOYMENT.md](LOCAL_INTRANET_DEPLOYMENT.md)—not required for a standard install.

---

## 2. Requirements

### Software

| Requirement | Details |
|-------------|---------|
| **PHP** | 8.3 or newer |
| **Composer** | PHP dependency manager ([getcomposer.org](https://getcomposer.org)) |
| **SQLite** | Built into PHP via PDO; no separate database server |
| **Node.js + npm** | To build frontend assets (Node 20+ and npm 10+ recommended) |
| **Web server** | **Optional** for beginners; `php artisan serve` is enough. nginx or Apache can be added later for always-on hosting. |

### PHP extensions

Your PHP install must include: **PDO SQLite**, mbstring, openssl, tokenizer, fileinfo, ctype, json, xml, and intl.

Check (Linux/macOS):

```bash
php -v
php -m | grep -E 'pdo_sqlite|mbstring|intl'
```

On Windows, use the PHP build or Laravel Herd that includes these extensions.

### Supported operating systems

- **Linux** (Ubuntu, Debian, Fedora, Arch/Manjaro, etc.)
- **Windows** 10/11 (PowerShell)
- **macOS** (same commands as Linux in Terminal)

Community Edition is intended for **trusted private networks** (home or studio). Do not expose it directly to the public internet without hardening you understand.

---

## 3. Downloading the application

Choose one method.

### Option A — Git clone (developers and updaters)

```bash
git clone <repository-url> easelogs
cd easelogs
```

Use the URL for the public **easelogs-community** repository when it is published.

### Option B — Release ZIP (recommended for many artists)

1. Download the latest Community Edition release ZIP from the project’s GitHub Releases page.
2. Extract to a permanent folder, for example:
   - Windows: `C:\EaseLogs`
   - Linux: `~/EaseLogs`
3. Open a terminal in that folder for all following commands.

---

## 4. Initial installation

From the project root (the folder that contains `artisan`):

**Linux / macOS:**

```bash
composer install
cp .env.example .env
php artisan key:generate
```

**Windows (PowerShell):**

```powershell
composer install
copy .env.example .env
php artisan key:generate
```

`key:generate` writes a unique `APP_KEY` in `.env`. Keep `.env` private; do not share it or commit it to git.

---

## 5. Database setup

Community Edition uses **SQLite**—one file on disk.

### Create the database file

**Linux / macOS:**

```bash
touch database/database.sqlite
```

**Windows (PowerShell):**

```powershell
New-Item database/database.sqlite
```

### Configure `.env`

Open `.env` and confirm (defaults in `.env.example` are usually correct):

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### Run migrations

```bash
php artisan migrate
```

This creates the tables EaseLogs needs. `php artisan db:seed` is **optional** and does not create your owner account or sample artworks for production use.

---

## 6. Storage setup

Uploaded artwork photos are stored under `storage/app/public/`. The web server must serve them through a symlink:

```bash
php artisan storage:link
```

**Why this is required:** Laravel saves files in `storage/app/public/`, but browsers load them from `public/storage/`. The link connects those paths. Without it, uploads succeed but **thumbnails and detail images stay broken**.

Run this **once per install** (and again after a fresh restore if `public/storage` is missing).

---

## 7. Build frontend assets

EaseLogs uses compiled CSS/JS for the interface:

```bash
npm install
npm run build
```

Re-run `npm run build` after upgrading to a new release if the upgrade instructions say to rebuild assets.

---

## 8. First startup

Start the built-in development server:

```bash
php artisan serve
```

Default URL on the same computer:

```text
http://127.0.0.1:8000/
```

To reach EaseLogs from a phone or tablet on your home network, use your computer’s LAN IP and bind to all interfaces:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Then open `http://YOUR-LAN-IP:8000/` from other devices (firewall rules may be required—see [COMMUNITY_EDITION.md](COMMUNITY_EDITION.md)).

**Note:** If you close the terminal, `php artisan serve` stops. For an always-on server, use nginx or Apache (advanced).

### First-run setup

When **no user account exists yet**, opening the site redirects you to:

```text
/setup
```

Complete the form to create your owner account. After that, you are signed in and can use the artwork inventory.

---

## 9. Creating the owner account

On first visit to `/setup` you provide:

- **Name**
- **Email**
- **Password** (and confirmation)

Important points:

- The **first user becomes the owner** of this install.
- There is **no registration system**—only this one-time setup when the database has no users.
- All artworks and CSV imports belong to this single-owner workflow.
- No default password is shipped with the app.

If you ever wipe the database with `php artisan migrate:fresh`, you must complete `/setup` again.

---

## 10. Logging in

After setup, sign out and sign in again at:

```text
/login
```

### Remember me

Check **Remember me** on a trusted home device to stay signed in longer.

### Profile management

Use **Profile** in the top navigation to view or edit your **name** and **email**.

### Password changes

From Profile, open **Change password**. You must enter your **current password** and a new password with confirmation.

Community Edition does not include forgot-password email (typical for offline, single-machine use). Keep your password safe or restore from backup if needed.

---

## 11. Basic workflow

### Create artwork

1. Go to **New artwork** (or `/artworks/create`).
2. Enter metadata: title (optional), dates, type, medium, dimensions, notes.
3. Save.

### Upload photo

On create or edit, attach a **photo** file. Community Edition focuses on one **primary** photo per artwork in the UI. Add or replace it from the artwork form.

### Mark completed

Set a **completed date** on the artwork when the piece is finished. Works without a completed date are treated as **in progress** in filters.

### Search

On the artwork index (`/artworks`), use **Search** to match **title** or **notes**, then click **Apply** (or press Enter in the search field).

### Filter

Use **quick filter** pills (for example in progress, completed, untitled, missing photo) or choose **type** and **medium**, then **Apply**.

### Sort

- **Default:** artworks sort by **most recently updated first** (`updated_at` descending). The **Recently updated** control returns to this default.
- **Desktop:** click column headers to sort.
- **Mobile:** use the **Sort** dropdown and **Apply** (cards replace the wide table).

### Import / export CSV

1. Open **Import / Export** (`/artworks/import-export`).
2. **Export CSV** downloads metadata only (no photos).
3. **Import CSV** adds new rows from a spreadsheet.

Approved columns:

```text
title,start_date,completed_date,artwork_type,medium,height,width,depth,dimension_unit,notes
```

Dates must be `YYYY-MM-DD`. Unsupported column names (inventory, photos, etc.) are rejected with an error. Add photos manually after import.

### Bulk delete

On the artwork index, select rows with checkboxes, then **Delete selected** and confirm. Use carefully; this removes database records.

---

## 12. Updating EaseLogs

Before any upgrade:

1. **Back up** `database/database.sqlite` and `storage/app/public/`. See [COMMUNITY_BACKUP.md](COMMUNITY_BACKUP.md).
2. Optionally **Export CSV** from the app for a readable copy of metadata.

### Typical upgrade steps

```bash
composer install
npm install
php artisan migrate
npm run build
```

Restart `php artisan serve` (or reload your web server).

### Preserve data

- Keep your existing `database/database.sqlite` unless you intentionally want a blank database.
- Keep `storage/app/public/` so photo files still match database paths.
- Run `php artisan storage:link` if photos do not display after restore or move.

**Avoid** `php artisan migrate:fresh` unless you intend to delete all artworks and users; back up first.

---

## 13. Troubleshooting

### Missing images / gray broken thumbnails

1. Run `php artisan storage:link`.
2. Confirm `public/storage` is a **symlink** to `storage/app/public` (not a broken path from an old machine).
3. Confirm files exist under `storage/app/public/artworks/`.

### Storage link issues

```bash
php artisan storage:link
```

If the command says the link already exists but images still fail, remove the broken `public/storage` entry (only if you understand the path), then run `storage:link` again.

### SQLite issues

- Ensure `database/database.sqlite` exists and `.env` points to it.
- Run `php artisan migrate` for schema updates.
- For corruption or experiments, restore from backup instead of deleting the file blindly.

### Permission issues (Linux)

The web server or your user must read and write:

```bash
chmod -R ug+rw storage bootstrap/cache database
```

### Cannot create artwork / redirected to setup

No user exists yet—complete `/setup`. If you reset the database, run `/setup` again.

### Port already in use

```bash
php artisan serve --host=0.0.0.0 --port=8080
```

### More help

- First visit, firewall, LAN access: [COMMUNITY_EDITION.md](COMMUNITY_EDITION.md)
- Backup and restore order: [COMMUNITY_BACKUP.md](COMMUNITY_BACKUP.md)

---

## 14. Related documentation

| Document | Purpose |
|----------|---------|
| [COMMUNITY_EDITION.md](COMMUNITY_EDITION.md) | Full Community guide: Windows/Linux detail, LAN, CSV, index UX |
| [COMMUNITY_BACKUP.md](COMMUNITY_BACKUP.md) | Backup paths, restore order, symlink and permissions |
| [DATABASE_INTERNAL_SCHEMA.md](DATABASE_INTERNAL_SCHEMA.md) | Database tables not used in Community UI |
| [LOCAL_INTRANET_DEPLOYMENT.md](LOCAL_INTRANET_DEPLOYMENT.md) | Optional nginx/php-fpm deploy (`easelogs.local`) |
| [README.md](../README.md) | Project overview and developer quick start |

---

Copyright and licensing: see [LICENSE](../LICENSE) and [LICENSE_OVERVIEW.md](../LICENSE_OVERVIEW.md) in the repository root.
