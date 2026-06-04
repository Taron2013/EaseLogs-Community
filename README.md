# EaseLogs

EaseLogs is a local-first artwork inventory and lifecycle management application for artists. It helps you catalog physical artwork, track creation and completion workflow, manage metadata, import and export CSV records, and attach photos.

EaseLogs is built with [Laravel](https://laravel.com) and SQLite. It is designed for self-hosted, offline-capable use with a simple backup model (copy the SQLite database and uploaded files). See [docs/COMMUNITY_BACKUP.md](docs/COMMUNITY_BACKUP.md).

EaseLogs was formerly developed under the working name ArtDoc.

## Purpose

EaseLogs Community Edition supports:

- Artwork inventory with title, dates, type, medium, dimensions, and notes
- Optional titles and backfill-friendly unnamed-work workflows
- Creation and completion dates; artwork index defaults to **most recently updated first**
- Quick filters (in progress, completed, untitled, missing photo/dimensions) plus search and sort
- Bulk delete on the artwork index
- First-run owner setup (`/setup`), sign-in (`/login`), profile and password change
- Artwork photos stored on your own server (run `php artisan storage:link` once per install)
- Metadata-only CSV import and export for spreadsheet workflows
- Responsive layout: desktop table and mobile card list with mobile sort controls
- Local SQLite storage for lightweight, artist-focused deployment

**Installation (Community Edition):** [docs/INSTALL_GUIDE.md](docs/INSTALL_GUIDE.md) — step-by-step setup for artists and self-hosters.

Additional documentation: [docs/COMMUNITY_EDITION.md](docs/COMMUNITY_EDITION.md) (platform details), [docs/COMMUNITY_BACKUP.md](docs/COMMUNITY_BACKUP.md) (backup/restore), [docs/DATABASE_INTERNAL_SCHEMA.md](docs/DATABASE_INTERNAL_SCHEMA.md) (internal schema notes).

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

Open [http://127.0.0.1:8000/](http://127.0.0.1:8000/) and complete first-run setup at `/setup` to create your owner account. For the full walkthrough (storage link, workflow, troubleshooting), see [docs/INSTALL_GUIDE.md](docs/INSTALL_GUIDE.md).

### Tests

```bash
./vendor/bin/phpunit
```

### Demo Mode

Public demo deployments can tune behavior with environment variables (no code changes required). When `EASELOGS_DEMO_MODE=false` (default), all demo flags are ignored and the app behaves normally.

**Upload behavior** (`EASELOGS_DEMO_UPLOAD_BEHAVIOR`) when demo mode is on:

| Value | Behavior |
|-------|----------|
| `enabled` | Normal photo uploads |
| `discard` | Upload UI visible; files removed before the controller — nothing stored or processed |
| `disabled` | Upload attempts with a photo return HTTP 403 |

### Public Demo Deployment

Recommended `.env` for a read-only public demo (pre-seed one owner account; do not expose setup):

```env
EASELOGS_DEMO_MODE=true
EASELOGS_DEMO_UPLOAD_BEHAVIOR=discard
EASELOGS_DEMO_ALLOW_IMPORTS=false
EASELOGS_DEMO_ALLOW_DELETES=false
EASELOGS_DEMO_ALLOW_ACCOUNT_CHANGES=false
EASELOGS_DEMO_ALLOW_REGISTRATION=false
EASELOGS_DEMO_ALLOW_PASSWORD_RESET=false
EASELOGS_DEMO_ALLOW_EMAIL_SENDING=false
EASELOGS_DEMO_ALLOW_EXTERNAL_WEBHOOKS=false
EASELOGS_DEMO_SHOW_PUBLIC_NOTICE=true
EASELOGS_DEMO_USER_NAME="Demo User"
EASELOGS_DEMO_USER_EMAIL="demo@easelogs.com"
EASELOGS_DEMO_USER_PASSWORD="change-this-demo-password"
EASELOGS_DEMO_SHOW_LOGIN_HINT=true
EASELOGS_DEMO_ALLOW_ONE_CLICK_LOGIN=true
```

Seed or refresh the demo account and sample artworks:

```bash
php artisan easelogs:demo-ensure
# Periodic full reset (e.g. cron):
php artisan easelogs:demo-reset --force
```

With demo mode on, `php artisan db:seed` also runs `DemoSeeder` (creates/updates the demo user and sample inventory).

With these settings, EaseLogs:

- Shows a site-wide demo banner on every page
- Lets visitors exercise upload forms but **discards** files (no storage, thumbnails, or `ArtworkPhoto` rows)
- Provides a configurable demo login (`EASELOGS_DEMO_USER_*`), optional credential hint and one-click login on the sign-in page
- Blocks profile/email/password changes (including the demo user), registration/setup, deletes, bulk delete, and CSV import (HTTP 403)
- Still allows artwork **metadata** create/edit so visitors can try the inventory UI; photo bytes follow upload behavior (`disabled` / `discard` / `enabled`)
- Forces mail to the `array` driver and cancels outbound mail notifications
- Blocks external webhooks and payment/license hooks via `DemoOutbound` guards (for future integrations)

Set any `EASELOGS_DEMO_ALLOW_*` flag to `true` only when you intentionally want that action in a demo environment. Backend middleware enforces restrictions even if the UI is bypassed.

See `.env.example` for the full variable list.

### Subdirectory deployment (URL prefix)

To mount EaseLogs under a path such as `/community` (common for shared demos or reverse proxies), set:

```env
APP_URL=https://demo.easelogs.com/community
EASELOGS_URL_PREFIX=community
```

Route names stay the same (`login`, `artworks.index`, etc.); `route()` and redirects include the prefix automatically. With no prefix (default), URLs remain `/login`, `/artworks`, and so on.

Configure your web server so requests under `/community` reach the Laravel `public` index. The health check route (`/up`) remains at the application root for load balancers.

If you previously ran `php artisan route:cache`, clear it after changing `EASELOGS_URL_PREFIX` so the prefix is rebuilt:

```bash
php artisan route:clear
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
./scripts/redeploy-local.sh              # interactive
./scripts/redeploy-local.sh --preserve   # keep SQLite + uploads
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

Optional verification after deploy:

```bash
./scripts/verify-local-deployments.sh
```

Full nginx, hosts, and TLS notes: [docs/LOCAL_INTRANET_DEPLOYMENT.md](docs/LOCAL_INTRANET_DEPLOYMENT.md).

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
| 8 | Complete | Artwork index sorting, filtering, and search |
| 9 | Planned | Dashboard improvements |
| 10 | Planned | Authentication and security expansion |
| 11 | Partial | Local intranet deployment documented; public cloud hosting guide planned |

## Licensing

**EaseLogs Community Edition** is licensed under the **[EaseLogs Community License](LICENSE)** — not MIT, and not public-domain.

- **Community use** — personal, educational, and non-commercial self-hosting as described in [LICENSE](LICENSE) and [LICENSE_OVERVIEW.md](LICENSE_OVERVIEW.md)
- **Commercial use** — requires a separate written commercial agreement ([COMMERCIAL_LICENSE.md](COMMERCIAL_LICENSE.md))

### License and contribution documents

| Document | Purpose |
|----------|---------|
| [LICENSE](LICENSE) | Full EaseLogs Community License |
| [LICENSE_OVERVIEW.md](LICENSE_OVERVIEW.md) | Plain-language summary and examples |
| [COMMERCIAL_LICENSE.md](COMMERCIAL_LICENSE.md) | When a commercial license is required |
| [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) | Attribution for Composer, npm, and framework dependencies |
| [CONTRIBUTING.md](CONTRIBUTING.md) | How to contribute; license and provenance expectations |

### Third-party software

EaseLogs depends on third-party packages that remain under **their own licenses** (see LICENSE Section 7 and [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md)).

- **[Laravel](https://laravel.com)** (`laravel/framework`) is **MIT-licensed**. Laravel is not owned by EaseLogs; MIT terms apply to Laravel code in `vendor/`, not to EaseLogs application source.
- Most PHP and JavaScript build dependencies are **MIT**, **BSD-3-Clause**, or **Apache-2.0**. Original license files in `vendor/` and `node_modules/` remain authoritative.
- **EaseLogs application code** (`app/`, `resources/views/`, etc.) is **not** relicensed under MIT or other dependency licenses.

## Copyright

Copyright © 2026 Douglas Cross. All rights reserved except as granted in LICENSE.
