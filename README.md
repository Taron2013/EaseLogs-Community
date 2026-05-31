# ArtDoc

ArtDoc is a local-first artwork inventory and lifecycle management application for artists. It helps you catalog physical artwork, track creation and completion workflow, manage metadata, and prepare for future photo documentation workflows.

ArtDoc is built with [Laravel](https://laravel.com) and SQLite. It is designed for self-hosted, offline-capable use with a simple backup model (copy the SQLite database and uploaded files).

## Purpose

ArtDoc supports:

- Artwork inventory records with flexible identifiers (inventory code, SKU)
- Optional titles and backfill-friendly unnamed-work workflows
- Creation and completion dates with lifecycle status rules
- Physical metadata (medium, dimensions, storage, value)
- Pattern-based auto-generation for inventory codes and SKUs
- Local SQLite storage for lightweight, artist-focused deployment

## Local development setup

### Requirements

- PHP 8.3+
- Composer
- Node.js and npm (for frontend assets)

### Quick start

```bash
git clone <repository-url> artdoc
cd artdoc

composer install
cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate

# Create at least one user (required for artwork records)
php artisan tinker
# >>> \App\Models\User::factory()->create();

npm install
npm run build

php artisan serve
```

Open [http://127.0.0.1:8000/artworks](http://127.0.0.1:8000/artworks).

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

It syncs your **current working tree** to the intranet deployment, runs Composer, `npm run build`, and fixes permissions for `storage`, `bootstrap/cache`, and `database`.

**Always preserved:**

* deployed `.env`
* artwork files under `storage/app/public/`

**Database handling (interactive prompt):**

| Choice | Behavior |
|--------|----------|
| **1 — Preserve** | Keeps `database/database.sqlite`, runs `php artisan migrate --force` |
| **2 — Reset** | Shows a CSV backup TODO, requires typing `RESET` to confirm, recreates SQLite, runs `php artisan migrate:fresh --force` |

After a database reset, run `php artisan db:seed` on the deploy copy to create the default Community user (`admin@easelogs.local` / `password`). Change that password before exposing the app beyond your trusted local network.

## Development roadmap

| Phase | Status | Description |
|-------|--------|-------------|
| 1 | Complete | Project setup |
| 2 | Complete | SQLite / local database configuration |
| 3 | Complete | Artwork schema and migrations |
| 4 | Complete | Models, relationships, auth foundation |
| 5 | Complete | SKU and inventory code generation |
| 6 | Complete | Artwork CRUD and metadata UX polish |
| 7 | In progress | Artwork image upload pipeline |
| 8 | Planned | Search, filter, and sorting |
| 9 | Planned | Dashboard improvements |
| 10 | Planned | Authentication and security expansion |
| 11 | Planned | Production readiness, deployment, documentation |

## Licensing

ArtDoc is **not** MIT-licensed as a whole. It uses a dual-license model:

- **Community use** — personal, educational, and non-commercial use under the [ArtDoc Community License](LICENSE)
- **Commercial use** — requires a separate written commercial agreement

Read these documents before use, modification, or redistribution:

- [LICENSE](LICENSE) — full ArtDoc Community License terms
- [LICENSE_OVERVIEW.md](LICENSE_OVERVIEW.md) — plain-language summary and examples
- [COMMERCIAL_LICENSE.md](COMMERCIAL_LICENSE.md) — commercial licensing requirements

### Third-party software

ArtDoc depends on third-party packages (including the Laravel framework) that remain licensed under their own terms. See LICENSE Section 7. Laravel is MIT-licensed; that license applies to Laravel itself, not to ArtDoc application code.

## Copyright

Copyright © 2026 Douglas Cross. All rights reserved except as granted in LICENSE.
