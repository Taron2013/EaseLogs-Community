# EaseLogs Community Edition

Private self-hosted artwork inventory for artists.

EaseLogs Community Edition allows artists to run a private artwork inventory website on their own computer and access it from devices on their home network.

Examples:

* Desktop computer
* Laptop
* Tablet
* Phone

Your artwork data remains under your control on your own machine.

---

## Downloading EaseLogs

Download the latest Community Edition release ZIP from the project’s GitHub Releases page (releases may still be published under the historical repository name ArtDoc):

```text
https://github.com/Taron2013/ArtDoc/releases
```

Extract the ZIP somewhere permanent.

Examples:

**Windows**

```text
C:\EaseLogs
```

**Linux**

```text
/home/yourusername/EaseLogs
```

or

```text
/var/www/easelogs
```

---

## System Requirements

Install the following before setup.

### PHP

**Version: 8.3 or newer**

EaseLogs requires PHP 8.3+.

Verify:

```bash
php -v
```

### Required PHP Extensions

Your PHP installation must include:

* PDO SQLite
* mbstring
* openssl
* tokenizer
* fileinfo
* ctype
* json
* xml
* intl

Verify:

```bash
php -m
```

### Composer

Verify:

```bash
composer --version
```

### Node.js + npm

Recommended:

* Node.js 20+
* npm 10+

Verify:

```bash
node -v
npm -v
```

### SQLite

Community Edition uses SQLite by default.

No separate database server is required.

---

## Important Community Edition Notes

### Keep the Terminal Window Open

EaseLogs stops running when the terminal window closes if started with:

```bash
php artisan serve
```

For permanent hosting, use a dedicated web server (advanced setup).

### Home Network Use Only

Community Edition is intended for trusted private networks only.

Do not expose directly to the public internet.

### Single owner account

Community Edition is **single-user**: one owner account per install. There is no public registration page.

- **First visit (no users):** you are redirected to **first-run setup** at `/setup` to create name, email, and password.
- **After setup:** sign in at `/login`. Use **Remember me** to stay signed in on trusted devices.
- **Profile:** update name and email from **Profile** in the navigation.
- **Password:** change password from Profile → **Change password** (current password required).

No default password is shipped. `php artisan db:seed` is optional and does not create your owner account.

### Backup your data

Copy `database/database.sqlite` and `storage/app/public/` regularly. Full steps: [COMMUNITY_BACKUP.md](COMMUNITY_BACKUP.md).

### Pro-reserved database tables

Some migrations create tables and columns used only in Pro or future releases. Community does not show them in the UI. See [SCHEMA_RESERVED_FOR_PRO.md](SCHEMA_RESERVED_FOR_PRO.md).

---

## Windows Setup

### Install Required Software First

Install:

* PHP 8.3
* Composer
* Node.js
* SQLite

Optional easier setup:

* Laravel Herd for Windows

After installing prerequisites, continue below.

### Step 1: Open PowerShell

```powershell
cd C:\EaseLogs
```

### Step 2: Install Dependencies

```powershell
composer install
npm install
```

### Step 3: Create Environment File

```powershell
copy .env.example .env
php artisan key:generate
```

### Step 4: Create SQLite Database

```powershell
New-Item database/database.sqlite
```

Edit `.env`:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### Step 5: Database Setup

```powershell
php artisan migrate
```

`php artisan db:seed` is optional (dev/demo data only). Create your owner account in the browser at `/setup` on first visit.

### Step 6: Build Frontend Assets

```powershell
npm run build
```

### Step 6b: Public storage link (required for photos)

```powershell
php artisan storage:link
```

### Step 7: Start EaseLogs

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

### Step 8: Open EaseLogs

On the same computer, open the site root (you will be sent to `/setup` on first visit):

```text
http://127.0.0.1:8000/
```

From another device:

```text
http://YOUR-PC-IP:8000/
```

Example:

```text
http://192.168.1.50:8000/
```

Complete **first-run setup**, then sign in at `/login` when prompted.

### Windows Firewall

If mobile devices cannot connect, allow inbound TCP port **8000** through Windows Defender Firewall.

---

## Linux Setup

### Step 1: Install System Packages

**Manjaro / Arch**

```bash
sudo pacman -S php composer nodejs npm sqlite nginx php-fpm
```

If extension checks in Step 2 fail, install the matching Arch `php-*` packages (for example `php-sqlite`, `php-intl`, `php-mbstring`).

**Ubuntu / Debian**

```bash
sudo apt update
sudo apt install php composer nodejs npm sqlite3 nginx php-fpm php-sqlite3 php-mbstring php-xml php-intl
```

### Step 2: Verify Extensions

```bash
php -m | grep -E 'pdo_sqlite|mbstring|openssl|intl'
```

Install any missing PHP modules.

### Step 3: Open Terminal

```bash
cd ~/EaseLogs
```

### Step 4: Install Dependencies

```bash
composer install
npm install
```

### Step 5: Create Environment File

```bash
cp .env.example .env
php artisan key:generate
```

### Step 6: Create SQLite Database

```bash
touch database/database.sqlite
```

Edit `.env`:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### Step 7: Database Setup

```bash
php artisan migrate
```

`php artisan db:seed` is optional (dev/demo data only). Create your owner account in the browser at `/setup` on first visit.

### Step 8: Build Assets

```bash
npm run build
```

### Step 8b: Public storage link (required for photos)

```bash
php artisan storage:link
```

### Step 9: Permissions

```bash
chmod -R ug+rw storage bootstrap/cache
```

### Step 10: Start EaseLogs

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### Step 11: Open EaseLogs

Local (first visit redirects to `/setup`):

```text
http://localhost:8000/
```

LAN:

```text
http://YOUR-LAN-IP:8000/
```

### Linux Firewall

**UFW**

```bash
sudo ufw allow 8000
```

**firewalld** (common on Manjaro)

```bash
sudo firewall-cmd --permanent --add-port=8000/tcp
sudo firewall-cmd --reload
```

---

## Accessing From Phone or Tablet

Find your computer's IP address.

**Windows**

```powershell
ipconfig
```

Look for **IPv4 Address**.

**Linux**

```bash
ip addr
```

Example:

```text
192.168.1.50
```

Open on mobile (same as desktop; layout adapts to small screens):

```text
http://192.168.1.50:8000/
```

---

## Artwork index: search, filters, and sort

The artwork list (`/artworks`) helps you find and organize work on desktop and mobile.

### Default sort

With no sort selected, artworks are ordered by **most recently updated first** (`updated_at` descending). The **Recently updated** quick control returns to this default.

### Search

Use **Search** to match **title** or **notes**. Click **Apply** to run the search (or press Enter in the search field).

### Quick filters

Pill links filter the list, for example:

- All artworks
- In progress / completed
- Untitled
- Missing photo / missing dimensions

Quick filters apply immediately (they are links). Type, medium, and search use the filter form **Apply** button.

### Sort (desktop and mobile)

- **Desktop:** click column headers (title, dates, type, medium, dimensions, updated).
- **Mobile:** use the **Sort** section (dropdown + **Apply**). The desktop table is hidden; artworks appear as cards.

### Clear vs reset

- **Clear filters** — removes quick/type/medium filters but keeps search text and sort.
- **Reset view** — clears filters, search, and sort back to the default listing.

Pagination keeps your current search, filters, and sort when you change pages.

### Bulk delete

Select artworks with the checkboxes, then use **Delete selected** (confirmation required). Use with care; deleted rows are removed from the database (photos on disk may remain until cleaned up manually).

---

## Mobile support

Community Edition is usable on phones and tablets on your home network:

- Sign in, profile, and password pages use a simple single-column layout.
- The artwork index shows **cards** instead of a wide table on narrow screens.
- Filters, sort, and bulk actions stack vertically for touch use.

Use `php artisan serve --host=0.0.0.0` and open your computer’s LAN IP from mobile devices (see firewall steps above).

---

## Deployment (advanced)

For day-to-day use, `php artisan serve` is enough. For a persistent copy on Linux with nginx (optional):

- Community intranet example: `https://easelogs.local` at `/var/www/projects/easelogs`
- Documented in [README.md](../README.md) and [LOCAL_INTRANET_DEPLOYMENT.md](LOCAL_INTRANET_DEPLOYMENT.md)
- Redeploy without losing data: `./scripts/redeploy-local.sh --preserve`

Pro is a separate product path (`easelogs.pro`, `/var/www/projects/easelog-pro`) and is not part of Community Edition packaging.

---

## Troubleshooting

### Cannot Create Artwork / First Visit

On a fresh install with no users, open EaseLogs in your browser. You are redirected to **first-run setup** (`/setup`) to create the owner account (name, email, password). No default password is shipped.

After setup, sign in at `/login` with the account you created.

### App Will Not Load

Ensure the server is running:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### Port Already In Use

```bash
php artisan serve --host=0.0.0.0 --port=8080
```

Then open `http://YOUR-IP:8080/`.

### Photos upload but do not display

Run once per install:

```bash
php artisan storage:link
```

Confirm `public/storage` is a symlink to `storage/app/public`.

### Permission Errors (Linux)

```bash
chmod -R ug+rw storage bootstrap/cache
```

### Database Problems

Prefer non-destructive fixes first:

```bash
php artisan migrate
```

**Warning — destroys all artwork and user data:**

```bash
php artisan migrate:fresh
```

Back up first: [COMMUNITY_BACKUP.md](COMMUNITY_BACKUP.md). After `migrate:fresh`, complete `/setup` again. Community seeders do not restore your inventory.

---

## Updating EaseLogs

When installing a newer release:

```bash
composer install
npm install
php artisan migrate
npm run build
```

If artwork creation fails after upgrading (no user record):

After reset, open the app and complete first-run setup at `/setup` to create the owner account.

Preserve:

* `database/database.sqlite`
* `storage/app/public`

See [COMMUNITY_BACKUP.md](COMMUNITY_BACKUP.md) for backup, restore, symlink, and permissions checks.

On Manjaro intranet deploys, code updates without wiping data:

```bash
./scripts/redeploy-local.sh --preserve
```

---

## CSV metadata import and export

Community Edition can move **metadata only** between spreadsheets and your inventory. Photos are **not** included in CSV files.

### Approved CSV columns (in order)

```text
title,start_date,completed_date,artwork_type,medium,height,width,depth,dimension_unit,notes
```

These files do **not** support: photos, `user_id`, internal IDs, timestamps, or financial/commercial fields.

### Export CSV

1. Open **Import / Export** in the top navigation (or go to `/artworks/import-export`).
2. Click **Export CSV**.
3. Save the downloaded file (for example `easelogs-artworks-2026-05-28.csv`).

The export uses the column order above. Empty fields are left blank.

### Import CSV

1. Prepare a `.csv` with a header row. Include any subset of approved column names; extra columns are ignored.
2. On the **Import / Export** page, use **Import CSV** and choose your file.
3. Dates must use `YYYY-MM-DD` format (for example `2026-05-28`).
4. Imported rows are added as **new** artworks for your local single-owner account.

If the file has disallowed Pro/legacy columns, invalid dates, or other errors, nothing is imported and an error message explains what to fix.

Add photos separately from the artwork create or edit screens after import.

---

## Community Edition Includes

* Artwork inventory (create, edit, delete, completion workflow)
* First-run setup (`/setup`) and sign-in (`/login`) with remember me
* Profile editing and password change
* Photo uploads (single primary photo per artwork in the UI)
* Thumbnail gallery and artwork detail pages
* Artwork index search, filters, sort (default: recently updated), and bulk delete
* Mobile-friendly index cards and mobile sort controls
* CSV metadata import and export (no photos in CSV)
* Single-owner local deployment and private self-hosting
* Documented backup paths (SQLite + `storage/app/public/`)

---

## Community Edition Does Not Include

* Cloud hosting or SaaS
* Multi-user accounts, roles, or collaboration
* OAuth / social login (see [AUTH_EXTENSIONS.md](AUTH_EXTENSIONS.md) for future direction)
* Forgot-password email flow (offline single-owner installs)
* Pro tables in the UI: events, tags, `app_settings` (see [SCHEMA_RESERVED_FOR_PRO.md](SCHEMA_RESERVED_FOR_PRO.md))
* SKU, valuation, inventory codes, and other commercial fields in forms or CSV
* Subscription services or enterprise deployment features

---

## Intended For

EaseLogs Community Edition is designed for:

* Individual artists
* Hobbyists
* Home studio inventory management

Your artwork inventory stays yours, hosted on your own machine.
