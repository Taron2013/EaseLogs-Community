# Database schema reserved for EaseLogs Pro

EaseLogs Community Edition and Pro share the same migration history in this repository. Community **does not expose** every table and column in the UI. Empty or unused structures are intentional placeholders for Pro features and forward-compatible upgrades.

**Do not delete these migrations from the Community codebase** before the Pro edition defines a supported upgrade path from Community installs.

## Tables not used by Community UI

| Table | Purpose (Pro / future) | Community behavior |
|-------|------------------------|--------------------|
| `artwork_events` | Status and location change history | Created by migrations; no Community routes or forms |
| `artwork_tags` | Tag definitions | No Community UI |
| `artwork_tag` | Artwork–tag pivot | No Community UI |
| `app_settings` | Application-wide settings store | No Community UI |

Models exist (`ArtworkEvent`, `ArtworkTag`, `AppSetting`) for schema consistency and future Pro work. Community installs may have **zero rows** in these tables.

## `artwork_photos` columns not exposed in Community

Community supports a **single primary photo** per artwork (upload/replace on create and edit). The table allows more columns and multiple rows for Pro workflows.

| Column | Community |
|--------|-----------|
| `file_path`, `is_primary`, `sort_order` | Used (primary photo workflow) |
| `caption` | Not editable in Community forms |
| `taken_at` | Not editable |
| `photo_type` | Stored as `general` when uploading |
| `progress_sequence` | Not used in Community |

## Artwork columns restricted in Community

Community forms and CSV import/export allow only the [approved metadata columns](COMMUNITY_EDITION.md#csv-metadata-import-and-export). Pro-oriented fields (SKU, valuation, inventory codes, client/provenance fields, etc.) exist in the schema or may be added in later migrations but are **blocked** in Community validation and CSV handling.

## Option for packagers

For a public **easelogs-community** repository split:

- **Keep** all migrations (recommended).
- **Document** this file in README and Community Edition guide so auditors understand unused tables are Pro-reserved, not missing features.

Removing dormant schema from Community-only migrations (split option B) is **not** recommended until Pro migration compatibility is defined.

## Related documentation

- [COMMUNITY_EDITION.md](COMMUNITY_EDITION.md) — what Community includes
- [AUTH_EXTENSIONS.md](AUTH_EXTENSIONS.md) — future OAuth / SSO (not in Community UI)
