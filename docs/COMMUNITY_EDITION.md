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

Download the latest Community Edition release ZIP from the GitHub Releases page:

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

### Internal Seeded User

During installation, EaseLogs creates an internal system user.

This user enables artwork creation.

Community Edition does not currently provide a login screen.

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

### Step 7: Start EaseLogs

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

### Step 8: Open EaseLogs

On the same computer:

```text
http://127.0.0.1:8000/artworks
```

From another device:

```text
http://YOUR-PC-IP:8000/artworks
```

Example:

```text
http://192.168.1.50:8000/artworks
```

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

### Step 9: Permissions

```bash
chmod -R ug+rw storage bootstrap/cache
```

### Step 10: Start EaseLogs

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### Step 11: Open EaseLogs

Local:

```text
http://localhost:8000/artworks
```

LAN:

```text
http://YOUR-LAN-IP:8000/artworks
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

Open on mobile:

```text
http://192.168.1.50:8000/artworks
```

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

Then open `http://YOUR-IP:8080/artworks`.

### Permission Errors (Linux)

```bash
chmod -R ug+rw storage bootstrap/cache
```

### Database Problems

```bash
php artisan migrate:fresh --seed
```

**Warning:** deletes all stored artwork data.

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

1. Prepare a `.csv` file with a header row using only approved column names (extra columns are rejected).
2. On the **Import / Export** page, use **Import CSV** and choose your file.
3. Dates must use `YYYY-MM-DD` format (for example `2026-05-28`).
4. Imported rows are added as **new** artworks for your local single-owner account.

If the file has unknown columns, invalid dates, or other errors, nothing is imported and an error message explains what to fix.

Add photos separately from the artwork create or edit screens after import.

---

## Community Edition Includes

* Artwork inventory
* Photo uploads
* Thumbnail gallery
* Artwork detail pages
* CSV metadata import and export (no photos in CSV)
* Single-user local deployment
* Private self-hosting

---

## Community Edition Does Not Include

* Cloud hosting
* Multi-user accounts
* Collaboration tools
* Subscription services
* Enterprise deployment features

---

## Intended For

EaseLogs Community Edition is designed for:

* Individual artists
* Hobbyists
* Home studio inventory management

Your artwork inventory stays yours, hosted on your own machine.
