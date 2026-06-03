# EaseLogs Community Edition — Backup and restore

EaseLogs stores your inventory in two places on disk. Copy both before upgrades, disk changes, or destructive database commands.

## What to back up

| Asset | Path (relative to install root) | Contains |
|-------|----------------------------------|----------|
| **Database** | `database/database.sqlite` | Artworks, metadata, user account, sessions |
| **Uploaded photos** | `storage/app/public/` | Image files (typically under `artworks/`) |

### Example install roots

| How you run EaseLogs | Typical root |
|----------------------|--------------|
| Development (`php artisan serve`) | Project folder, e.g. `~/EaseLogs` |
| Local nginx (Community) | `/var/www/projects/easelogs` |
| Release ZIP extract | Where you extracted the ZIP, e.g. `C:\EaseLogs` |

Back up from the **same folder** that contains `artisan`, `database/`, and `storage/`.

## What is not required for a basic restore

- `vendor/` and `node_modules/` — reinstall with `composer install` and `npm install` after restore.
- `public/build/` — rebuild with `npm run build`.
- `.env` — optional to copy; if you restore only the database to a **new** install, you need a valid `APP_KEY` in `.env` (generate with `php artisan key:generate` on first install). Restoring an old `.env` together with the old database keeps login sessions consistent.

## Backup procedure

1. **Stop or quiesce** the app if possible (stop `php artisan serve`, or avoid editing artworks during copy).
2. Copy the database file:
   ```bash
   cp database/database.sqlite /path/to/your-backups/easelogs-$(date +%Y%m%d).sqlite
   ```
3. Copy uploaded files (entire public storage tree):
   ```bash
   cp -a storage/app/public /path/to/your-backups/easelogs-storage-$(date +%Y%m%d)
   ```
4. Optionally export CSV metadata from **Import / Export** in the app for a human-readable spreadsheet copy (photos are not in CSV).

## Restore procedure (recommended order)

1. Install or unpack the EaseLogs version you want to run.
2. Run `composer install`, `npm install`, `npm run build`, and `php artisan migrate` on the **empty** install if this is a fresh tree (see warnings below if replacing an existing DB).
3. **Restore the database:**
   ```bash
   cp /path/to/your-backups/easelogs-YYYYMMDD.sqlite database/database.sqlite
   ```
4. **Restore uploads:**
   ```bash
   cp -a /path/to/your-backups/easelogs-storage-YYYYMMDD/. storage/app/public/
   ```
5. **Storage symlink** (required for photos to display):
   ```bash
   php artisan storage:link
   ```
   Verify `public/storage` points at `storage/app/public` (symlink, not a stale path from an old machine).
6. **Permissions** (Linux / nginx):
   ```bash
   chmod -R ug+rw storage bootstrap/cache database
   ```
   If using nginx/php-fpm, ensure the web server user can read `storage/` and write `storage/logs`, `bootstrap/cache`, and `database/`.
7. Open the site and sign in at `/login` with the account from your backup.

If thumbnails are missing but files exist on disk, the symlink or permissions step is usually the cause.

## Before destructive commands

These operations **delete or replace** artwork data unless you have a backup:

| Command / action | Risk |
|------------------|------|
| `php artisan migrate:fresh` | Drops all tables; empty database |
| `php artisan migrate:fresh --seed` | Same; Community seeders do not recreate your inventory |
| Redeploy script **Reset** mode | Recreates SQLite; uploads may remain orphaned on disk |
| Deleting `database/database.sqlite` | All metadata and user account lost |

**Before reset:** copy `database/database.sqlite` and `storage/app/public/`, and consider **Export CSV** from the app.

## Redeploy with data preserved

On a Manjaro intranet Community deploy:

```bash
./scripts/redeploy-local.sh --preserve
```

This keeps deployed `.env`, `database/database.sqlite`, and `storage/app/public/` while updating code and assets.

See [LOCAL_INTRANET_DEPLOYMENT.md](LOCAL_INTRANET_DEPLOYMENT.md) for the optional `easelogs.local` nginx path.

## Verify after restore

- Sign in at `/login`.
- Open the artwork index; recently updated works should appear (default sort: most recently updated first).
- Open an artwork with a photo; thumbnail and detail image load.
- If import/export was used before backup, spot-check row counts or a known title.

## Related documentation

- [COMMUNITY_EDITION.md](COMMUNITY_EDITION.md) — install and daily use
- [README.md](../README.md) — development setup and redeploy overview
