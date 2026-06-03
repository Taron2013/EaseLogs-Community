# Third-party notices

**EaseLogs Community Edition** application source code is licensed under the [EaseLogs Community License](LICENSE) (see also [LICENSE_OVERVIEW.md](LICENSE_OVERVIEW.md)). **This file is informational.** It does not relicense EaseLogs application code under third-party terms.

Third-party packages retain their **original licenses**. The authoritative license text for each package is in that package’s own files (for example under `vendor/<package>/` after `composer install`, or in `node_modules/<package>/` after `npm install`).

---

## Summary

| Component | Role | License profile |
|-----------|------|-----------------|
| EaseLogs application (`app/`, `resources/`, `routes/`, etc.) | Your inventory app | [EaseLogs Community License](LICENSE) |
| PHP dependencies (`composer.json` / `composer.lock`) | Runtime + dev tooling | Mostly **MIT** and **BSD-3-Clause**; see [Nette note](#nette-dual-license-packages) |
| JavaScript build dependencies (`package.json` / `package-lock.json`) | Asset build only (`npm run build`) | Mostly **MIT** and **ISC**; includes **MPL-2.0** build tools (see [npm note](#javascript--frontend-build-dependencies)) |
| [Laravel](https://laravel.com) framework | PHP framework (via Composer) | **MIT** |

---

## Laravel framework (MIT)

EaseLogs is built on the [Laravel](https://laravel.com) framework (`laravel/framework`), which is distributed under the **MIT License**.

- Laravel is **not** owned by EaseLogs.
- The MIT License applies to **Laravel and its separately licensed components**, not to EaseLogs application code.
- When you distribute or deploy EaseLogs, comply with Laravel’s license for the Laravel code included in `vendor/` (copyright and permission notices).

EaseLogs also uses standard Laravel application structure (for example `bootstrap/app.php`, `public/index.php`, default user/session migrations). That skeleton is part of the Laravel ecosystem and remains under Laravel’s MIT terms when present in `vendor/`.

---

## Nette dual-license packages

Composer may install:

| Package | Declared licenses | How EaseLogs uses them |
|---------|-------------------|-------------------------|
| `nette/schema` | BSD-3-Clause / GPL-2.0-only / GPL-3.0-only | Transitive dependency (via Laravel); **BSD-3-Clause** election |
| `nette/utils` | BSD-3-Clause / GPL-2.0-only / GPL-3.0-only | Transitive dependency (via Laravel); **BSD-3-Clause** election |

EaseLogs does **not** treat these packages as GPL-only dependencies. Use and redistribution follow the **BSD-3-Clause** option, consistent with typical Laravel application distribution.

---

## Framework / skeleton attribution

The following are **Laravel-ecosystem patterns**, not standalone products:

| Area | Notes |
|------|--------|
| `public/index.php`, `artisan`, `bootstrap/app.php` | Standard Laravel bootstrap |
| `database/migrations/0001_01_01_000000_create_users_table.php` | Default users/sessions/password reset schema |
| `database/factories/UserFactory.php` | Laravel factory pattern |
| `resources/views/vendor/pagination/easelogs.blade.php` | Custom pagination view using Laravel’s paginator **API** (EaseLogs markup/CSS) |
| `resources/views/artworks/pagination.blade.php` | Includes the vendor pagination view for `links('artworks.pagination')` |

Other published Laravel default pagination templates (Bootstrap/Tailwind) are **not** shipped in this repository.

---

## PHP runtime dependencies (direct)

Installed in production with `composer install --no-dev` (see `composer.json` `require`):

| Package | Version (lock) | License | Source |
|---------|----------------|---------|--------|
| `laravel/framework` | v13.11.2 | MIT | https://laravel.com |
| `laravel/tinker` | v3.0.2 | MIT | https://github.com/laravel/tinker |

PHP itself is required (^8.3); see [php.net](https://www.php.net/) license terms separately.

---

## PHP development dependencies (direct)

Installed with full `composer install` (see `composer.json` `require-dev`):

| Package | Version (lock) | License | Source |
|---------|----------------|---------|--------|
| `fakerphp/faker` | v1.24.1 | MIT | https://github.com/FakerPHP/Faker |
| `laravel/pail` | v1.2.6 | MIT | https://github.com/laravel/pail |
| `laravel/pao` | v1.0.6 | MIT | https://github.com/laravel/pao |
| `laravel/pint` | v1.29.1 | MIT | https://github.com/laravel/pint |
| `mockery/mockery` | 1.6.12 | BSD-3-Clause | https://github.com/mockery/mockery |
| `nunomaduro/collision` | v8.9.4 | MIT | https://github.com/nunomaduro/collision |
| `phpunit/phpunit` | 12.5.26 | BSD-3-Clause | https://github.com/sebastianbergmann/phpunit |

---

## PHP transitive dependencies

`composer install` pulls in additional transitive packages (Symfony components, Guzzle, Carbon, etc.).

**License summary (full lock file, 109 packages):**

| License | Package count |
|---------|----------------|
| MIT | 77 |
| BSD-3-Clause | 29 |
| BSD-3-Clause / GPL-2.0-only / GPL-3.0-only | 2 (`nette/schema`, `nette/utils`) |
| Apache-2.0 | 1 (`phpoption/phpoption`) |

**Full list:** [docs/THIRD_PARTY_NOTICES_COMPOSER_APPENDIX.md](docs/THIRD_PARTY_NOTICES_COMPOSER_APPENDIX.md) (generated from `composer.lock`).

Regenerate the appendix after lock file changes:

```bash
php scripts/generate-third-party-notices.php
```

---

## JavaScript / frontend build dependencies

Declared in `package.json` as **devDependencies** (build-time only). The Community UI primarily uses **inline CSS in Blade layouts**; `npm run build` produces assets under `public/build/` for installs that use the Vite pipeline (see `vite.config.js`).

### Direct npm packages

| Package | License | Source |
|---------|---------|--------|
| `vite` | MIT | https://github.com/vitejs/vite |
| `laravel-vite-plugin` | MIT | https://github.com/laravel/vite-plugin |
| `tailwindcss` | MIT | https://github.com/tailwindlabs/tailwindcss |
| `@tailwindcss/vite` | MIT | https://github.com/tailwindlabs/tailwindcss |
| `concurrently` | MIT | https://github.com/open-cli-tools/concurrently |

### Transitive npm license summary

From `package-lock.json` (59 packages audited):

| License | Count |
|---------|------:|
| MIT | 46 |
| ISC | 6 |
| Apache-2.0 | 2 |
| MPL-2.0 | 2 |
| BSD-3-Clause | 1 |
| 0BSD | 1 |

**MPL-2.0 (build tooling):** `lightningcss` and `lightningcss-linux-x64-gnu` (Tailwind/Vite toolchain). These are **not** EaseLogs application code; they participate only in `npm run build`. Comply with MPL-2.0 if you redistribute those build artifacts or source bundles.

**Fonts:** `vite.config.js` may fetch **Instrument Sans** via Laravel Vite’s Bunny Fonts integration. If font files are bundled into `public/build/`, the font license (typically SIL Open Font License for Instrument Sans) applies to those font files.

Pin versions in `package-lock.json`. Regenerate with `npm install` when `package.json` changes.

---

## Commercial licensing (EaseLogs)

Community use of **EaseLogs itself** is governed by the [EaseLogs Community License](LICENSE). **Commercial use** of EaseLogs requires a separate agreement — see [COMMERCIAL_LICENSE.md](COMMERCIAL_LICENSE.md).

Third-party MIT/BSD/Apache/MPL licenses **do not** grant commercial rights to EaseLogs application code.

---

## How to verify licenses locally

```bash
composer licenses
composer licenses --format=json

npm install
npx license-checker --summary
```

---

## Document history

- Generated for public **easelogs-community** release compliance review.
- Composer data from `composer.lock`; npm data from `package-lock.json`.
- See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution and license compatibility requirements.
