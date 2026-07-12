# Database schema — internal and reserved tables

EaseLogs Community Edition runs migrations that create some tables and columns **not used in the Community UI**. These exist for forward-compatible upgrades and possible future features. Community installs may have **zero rows** in the unused tables.

**Do not delete these migrations** from a Community install unless you understand you are breaking compatibility with databases created from the standard migration set.

## Tables not exposed in Community UI

| Table | Intended use (future / internal) | Community behavior |
|-------|----------------------------------|--------------------|
| `artwork_events` | Status and location change history | Migrated; no Community routes or forms |
| `artwork_tags` | Tag definitions (Style / Subject / General) | Tag settings + artwork forms; see [ARTWORK_TAGS.md](ARTWORK_TAGS.md) |
| `artwork_tag` | Artwork–tag pivot | Used with typed tags on artworks |
| `app_settings` | Application-wide settings store | No Community UI |

Models exist (`ArtworkEvent`, `ArtworkTag`, `AppSetting`) for schema consistency. They are not required for day-to-day Community use.

## `artwork_photos` columns not exposed in Community

Community supports a **single primary photo** per artwork (upload/replace on create and edit). Additional columns support possible future workflows.

| Column | Community |
|--------|-----------|
| `file_path`, `is_primary`, `sort_order` | Used (primary photo workflow) |
| `caption` | Not editable in Community forms |
| `taken_at` | Not editable |
| `photo_type` | Stored as `general` when uploading |
| `progress_sequence` | Not used in Community |

## Artwork metadata restricted in Community

Community forms and CSV import/export allow only the [approved metadata columns](COMMUNITY_EDITION.md#csv-metadata-import-and-export). Extra column names (inventory codes, valuation fields, photo paths in CSV, etc.) are **rejected** by validation even if they appear in older or extended schemas.

## Related documentation

- [COMMUNITY_EDITION.md](COMMUNITY_EDITION.md) — what Community includes
- [AUTH_EXTENSIONS.md](AUTH_EXTENSIONS.md) — future OAuth / SSO (not in Community UI)
