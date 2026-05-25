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

User-uploaded artwork files (when enabled) are stored under `storage/app/public/artworks/`. Run `php artisan storage:link` before serving public uploads.

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
