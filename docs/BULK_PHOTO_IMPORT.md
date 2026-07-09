# Bulk photo import (Community Edition)

EaseLogs Community Edition can backfill artwork photos from a ZIP package and an optional mapping CSV.

## Workflow

1. Build a ZIP of photo files (filenames are used for automatic matching).
2. Optionally add a mapping CSV to override matches with `artwork_id` or a title candidate.
3. Open **Import / Export → Bulk photo import** and upload the ZIP.
4. Review the preview — every photo receives a best-effort match from its filename unless the CSV overrides it.
5. Check the boxes for title or filename matches you want to import.
6. Apply ready rows and confirmed matches, or cancel to discard cached files.

You do **not** need a mapping CSV for a normal bulk import.

### Matching order (per photo)

1. **CSV `artwork_id`** when the optional mapping CSV covers that file (imports as ready).
2. **CSV title candidate** (`title`, `artwork_title`, or `name`) when provided for that file.
3. **Normalized filename title** for all other photos in the ZIP.

### Filename normalization

Trailing artwork identifiers are stripped before title matching, for example:

| Filename | Parsed title |
|----------|----------------|
| `Aurora Borealis Waterfall-2022#11.jpg` | Aurora Borealis Waterfall |
| `A storm-2023#101-11272023.jpg` | A storm |
| `Abstract Key-2024#14.jpg` | Abstract Key |
| `Blue-Heron-Final.jpg` | Blue Heron |

Preview does not write photos or change artworks.

Title matching rules (CSV or filename):

- Exact title match only (case-insensitive).
- Never auto-imports title matches silently.
- Never matches artworks titled **Untitled**.
- Ambiguous duplicate titles are reported and not imported.
- `inventory_code` and `sku` are **Pro-only** and are rejected if present in a CE mapping CSV.

## Mapping CSV (optional)

Supported columns:

| Column | Required | Notes |
|--------|----------|-------|
| `filename` | Yes (when using CSV) | Path inside the ZIP, e.g. `photos/blue.jpg` |
| `artwork_id` | Optional | Direct match when owned by your account |
| `title`, `artwork_title`, or `name` | Optional | Title candidate only; requires preview confirmation |
| `set_as_current` | No | `1`, `true`, or `yes` to make the imported photo current |
| `caption` | No | Optional caption stored on the photo record |

Pro-only columns (`inventory_code`, `sku`) are rejected with an error.

Include `mapping.csv` or `manifest.csv` in the ZIP, or upload separately. If no mapping CSV is provided, every image in the ZIP is evaluated using filename title parsing.

Supported photo types: JPEG, PNG, WebP (as configured in `config/easelogs.php`).

## Preview report

The summary shows:

- **Archive photos** — images found in the ZIP
- **Matched photos** — ready rows plus title/filename matches awaiting confirmation
- **Automatic matches** — filename matches awaiting confirmation
- **Title matches awaiting confirmation** — CSV title matches awaiting confirmation
- **Photos ready to import** — explicit `artwork_id` rows
- **Ambiguous / unmatched / skipped** counts

Each row uses short notes such as `✓ Filename match`, `✓ Exact title match`, `⚠ Ambiguous title match`, or `✗ No artwork found`.

The preview table shows a lazy-loaded thumbnail for each ZIP image (served from the temporary extract during the preview session only). Title and filename matches are checked by default; use **Apply import** to confirm the import.

## Completed artworks

Bulk import may add photos to completed artworks without extra confirmation.

## After apply or discard

Applying imports eligible rows, stores photos under `storage/app/public/artworks/{artwork_id}/`, and deletes the temporary ZIP extract and preview cache.

Canceling (discard) removes the preview cache and temporary files without importing anything. Unmatched photos are not staged for later reconciliation in Community Edition.

## Upload size limits

Community Edition defaults to a **4096 MB (4 GB)** app-level limit for the photo ZIP (`EASELOGS_PHOTO_IMPORT_MAX_UPLOAD_MB` in `.env`, or `photo_import_max_upload_mb` in `config/easelogs.php`). Values of `0` or less fall back to 4096 MB — Community Edition does not disable the Laravel limit.

Real artwork JPEGs are often 20+ MB each; multi-gigabyte ZIPs are normal for backfill imports.

### Effective limit (three layers)

The largest ZIP that can succeed is the **minimum** of:

| Layer | Setting | Who configures |
|-------|---------|----------------|
| EaseLogs app | `EASELOGS_PHOTO_IMPORT_MAX_UPLOAD_MB` | `.env` / `config/easelogs.php` |
| PHP | `post_max_size`, `upload_max_filesize` | System administrator (`php.ini`, PHP-FPM pool) |
| Nginx | `client_max_body_size` | System administrator (`easelogs.local.conf`) |

EaseLogs cannot read the Nginx limit from PHP. If Nginx/PHP allow 6144M but the CE app limit is 4096M, a 4.5 GB archive fails **without HTTP 413** — Laravel rejects it and shows an error on Import / Export.

### Diagnostics

- **Import / Export page** — shows the effective ZIP limit and warnings when PHP limits are lower than the app limit.
- **`GET /health/photo-import-upload`** — JSON report (`status`: `ok`, `degraded`, or `misconfigured`).

```bash
curl -s https://easelogs.local/health/photo-import-upload | jq
```

### HTTP 413 Request Entity Too Large

If the browser or curl reports **413**, Nginx (or another reverse proxy) rejected the upload **before** Laravel ran. Raise limits outside Laravel:

| Layer | Setting | Community local example |
|-------|---------|-------------------------|
| Nginx | `client_max_body_size` | `4096M` or higher in `easelogs.local.conf` |
| PHP | `upload_max_filesize` | `4096M` or higher |
| PHP | `post_max_size` | Same or larger than `upload_max_filesize` |

Example nginx snippet:

```nginx
client_max_body_size 4096M;
```

See `deploy/nginx/easelogs.local.conf.example` and [LOCAL_INTRANET_DEPLOYMENT.md](LOCAL_INTRANET_DEPLOYMENT.md).

After changing Nginx or PHP, reload the service and retry the upload.

## ZIP extraction safeguards

Before extracting an uploaded photo ZIP, EaseLogs validates archive contents to reduce Zip Slip and resource-exhaustion risks:

- Rejects path traversal (`..`), absolute paths, null bytes, and duplicate paths
- Rejects symbolic link entries
- Enforces limits on entry count, total uncompressed size, per-file uncompressed size, path depth, and path length

Defaults are configured in `config/easelogs.php` under `photo_import_zip` and can be overridden via `.env`:

| Setting | Default |
|---------|---------|
| `EASELOGS_PHOTO_IMPORT_ZIP_MAX_ENTRIES` | 2000 |
| `EASELOGS_PHOTO_IMPORT_ZIP_MAX_TOTAL_UNCOMPRESSED_MB` | 15360 (15 GB) |
| `EASELOGS_PHOTO_IMPORT_ZIP_MAX_ENTRY_UNCOMPRESSED_MB` | 25 |
| `EASELOGS_PHOTO_IMPORT_ZIP_MAX_PATH_DEPTH` | 10 |
| `EASELOGS_PHOTO_IMPORT_ZIP_MAX_PATH_LENGTH` | 255 |

Unsafe archives are rejected with an error on the Import / Export page; no files are extracted outside the temporary import directory.
