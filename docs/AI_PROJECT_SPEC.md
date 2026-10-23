# Community Edition Product Specification

**Audience:** Community Edition maintainers and contributors  
**Status:** Maintainer context; the application repository remains the implementation source of truth.  
**Last reviewed:** 2026-10-23

## Product purpose

EaseLogs Community Edition is a local-first artwork inventory and lifecycle-management application for artists. It helps an artist catalog physical work, follow creation and completion, keep useful metadata, and preserve artwork photos and records under the artist's control.

## Users and core jobs

The primary user is an individual artist maintaining a personal studio inventory. The product should make it practical to:

- Record a work, including optional title, dates, type, medium, dimensions, notes, and status-relevant details.
- Find works by search, sort, and focused filters.
- Attach and review artwork photos.
- Import and export artwork metadata through CSV.
- Operate the application on a self-hosted system, including an offline-capable local installation.

## Current product capabilities

The following reflects the Community README and documentation present on the reviewed branch; verify implementation and tests before changing behavior.

- Artwork record create, read, update, and delete workflows, with unnamed-work/backfill support.
- Artwork list search, sort, and filters for in-progress, completed, untitled, missing photo, and missing dimensions.
- Artwork photo upload and display, stored on the installation's own server.
- Metadata-only CSV import and export.
- Artwork tags and bulk photo import with preview and reconciliation, documented in the repository.
- First-run owner setup, sign-in, and owner profile/password management.
- Responsive desktop and mobile views.
- SQLite-based local deployment and documented backup/restore.

## Product and implementation constraints

- Keep Community Edition documentation focused on Community workflows and describe its capabilities positively.
- Preserve local-first, self-hosted operation and a straightforward backup model.
- Treat artwork metadata and uploaded photos as user data. Changes to storage, imports, or deletion require explicit data-integrity and recovery consideration.
- Keep authorization and upload validation enforced on the server; UI restrictions alone are insufficient.
- Use the repository's existing Laravel conventions, migrations, tests, license, and contribution rules.
- Do not introduce external services, paid dependencies, or cloud assumptions without an approved product decision.
- Before coding, inspect current code, tests, branch state, and linked design docs. A design note does not override code evidence or a later approved decision.

## Canonical implementation references

- [Community Edition overview](COMMUNITY_EDITION.md)
- [Installation guide](INSTALL_GUIDE.md)
- [Backup and restore](COMMUNITY_BACKUP.md)
- [Artwork tags](ARTWORK_TAGS.md)
- [Bulk photo import](BULK_PHOTO_IMPORT.md)
- [Authorization extensions](AUTH_EXTENSIONS.md)
- [Internal database schema](DATABASE_INTERNAL_SCHEMA.md)

## Relationship to backlog and work status

The edition-specific candidate backlog is [AI_BACKLOG.md](AI_BACKLOG.md). Product requirements and backlog entries live in this application repository. A project swarm repository may keep a synchronized execution copy and its live worker status; update the source requirement/backlog here whenever an approved decision changes it.

A backlog item marked as a candidate is not authorization to implement it. Before implementation, the coordinator must identify the exact item, record human approval in the swarm decision/status records, and provide the approved acceptance criteria.
