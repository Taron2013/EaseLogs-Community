# EaseLogs

EaseLogs is a local-first artwork inventory and lifecycle management application for artists. It helps you catalog physical artwork, track creation and completion workflow, manage metadata, import and export CSV records, and attach photos.

EaseLogs is built with [Laravel](https://laravel.com) and SQLite. It is designed for self-hosted, offline-capable use with a simple backup model (copy the SQLite database and uploaded files).

EaseLogs was formerly developed under the working name ArtDoc.

## Purpose

EaseLogs Community Edition supports:

- Artwork inventory with title, dates, type, medium, dimensions, and notes
- Optional titles and backfill-friendly unnamed-work workflows
- Creation and completion dates with in-progress-first artwork listing
- Artwork photos stored on your own server
- Metadata-only CSV import and export for spreadsheet workflows
- Local SQLite storage for lightweight, artist-focused deployment

## Local development setup

### Requirements

- PHP 8.3+
- Composer
- Node.js and npm (for frontend assets)

### Quick start

```bash
git clone <repository-url> easelogs
cd easelogs

composer install
cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate

npm install
npm run build

php artisan serve
```

Open [http://127.0.0.1:8000/](http://127.0.0.1:8000/) and complete first-run setup at `/setup` to create your owner account.

### Tests

```bash
./vendor/bin/phpunit
```

### Storage

Artwork photos are stored under `storage/app/public/artworks/`.

**Required once per install** — create the public storage symlink so uploaded photos are visible in the browser:

```bash
php artisan storage:link
```

Without this step, photo uploads save correctly but thumbnails and detail images will not display.

### Local intranet redeploy (Manjaro)

For testing deploys to the nginx/php-fpm copy at `https://easelogs.local` (`/var/www/projects/easelogs`), run from the project root:

```bash
chmod +x scripts/redeploy-local.sh
./scripts/redeploy-local.sh
```

**LOCAL `easelogs.local` only** — not for remote production. The script will evolve over time.

It syncs your **current working tree** to the intranet deployment (including `scripts/`, Blade views such as `resources/views/artworks/pagination.blade.php`, and PHP app code), runs Composer, `npm run build`, and fixes permissions for `storage`, `bootstrap/cache`, and `database`.

Before syncing, the script verifies required deploy paths exist in your source tree. After syncing, it re-syncs `scripts/` explicitly, marks `*.sh` executable on the server, and verifies the deploy tree again (so missing views like the artworks pagination partial fail fast instead of causing HTTP 500).

**Always preserved:**

* deployed `.env`
* artwork files under `storage/app/public/` (not overwritten by rsync from dev)

**Database handling (interactive prompt):**

| Choice | Behavior |
|--------|----------|
| **1 — Preserve** | Keeps `database/database.sqlite` and deploy `storage/app/public/`, runs `php artisan migrate --force`. Photo paths in SQLite must match files on disk. |
| **2 — Reset** | Shows a CSV backup TODO, requires typing `RESET` to confirm, recreates SQLite, runs `php artisan migrate:fresh --force`. Old upload files may remain on disk but no DB rows reference them. |

The redeploy script always verifies `public/storage` → `storage/app/public`. A broken symlink (for example pointing at an old project path) causes gray broken thumbnails even when files exist.

After a database reset, open the app in your browser and complete **first-run setup** at `/setup` to create the owner account. No default password is created by seeders.

## Development roadmap

| Phase | Status | Description |
|-------|--------|-------------|
| 1 | Complete | Project setup |
| 2 | Complete | SQLite / local database configuration |
| 3 | Complete | Artwork schema and migrations |
| 4 | Complete | Models, relationships, auth foundation |
| 5 | Complete | Artwork CRUD and metadata UX |
| 6 | Complete | Artwork photo uploads |
| 7 | Complete | CSV metadata import and export |
| 8 | Complete | Artwork index sorting |
| 9 | Planned | Search and filtering |
| 10 | Planned | Dashboard improvements |
| 11 | Planned | Authentication and security expansion |
| 12 | Planned | Production readiness, deployment, documentation |

## Licensing

EaseLogs is **not** MIT-licensed as a whole. It uses a dual-license model:

- **Community use** — personal, educational, and non-commercial use under the [EaseLogs Community License](LICENSE)
- **Commercial use** — requires a separate written commercial agreement

Read these documents before use, modification, or redistribution:

- [LICENSE](LICENSE) — full EaseLogs Community License terms
- [LICENSE_OVERVIEW.md](LICENSE_OVERVIEW.md) — plain-language summary and examples
- [COMMERCIAL_LICENSE.md](COMMERCIAL_LICENSE.md) — commercial licensing requirements

### Third-party software

EaseLogs depends on third-party packages (including the Laravel framework) that remain licensed under their own terms. See LICENSE Section 7. Laravel is MIT-licensed; that license applies to Laravel itself, not to EaseLogs application code.

## Copyright

Copyright © 2026 Douglas Cross. All rights reserved except as granted in LICENSE.
