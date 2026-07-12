# Artwork tags (Community Edition)

Community Edition uses a single **Tags** field on artwork forms. Tags are optional and you do not need to configure them before creating artwork.

## How tags are stored

Every tag created in Community Edition is stored with type **general** in the database. The `type` column exists for compatibility with EaseLogs Pro and future upgrades.

### Upgrading to Pro

Tags you create in Community Edition remain **General** tags in Pro. After moving to Pro you can:

- Reclassify tags as **Style** or **Subject** from Pro tag settings
- Use Pro-only merge and bulk-update tools for catalog cleanup

No upgrade wizard is required; data is already in the shared schema.

### Legacy or imported typed tags

If a database contains Style or Subject tags (for example from a Pro export or manual SQL), Community Edition:

- **Displays** them on artworks in the combined Tags list (without type labels)
- **Does not** expose controls to create or reclassify typed tags
- **Preserves** Style/Subject artwork associations when you edit General tags on an artwork

## Entering tags on artwork forms

The **Tags** control supports:

- Removable chips for assigned General tags
- Selecting an existing tag from suggestions
- Typing a new tag and pressing **Add**
- Comma-separated input in the add field (e.g. `Landscape, Dog, Bird`)

Parsing rules:

- Split on commas, trim whitespace, ignore empty values
- Case-insensitive duplicate prevention within the same artwork
- Reuse existing tags with the same normalized name

## Display and filtering

Tags appear in one **Tags** group on artwork show pages, index rows, and mobile cards.

The artwork index provides a single **Tag** autocomplete filter when you have tags. Search also matches tag names.

## Tag settings (Settings → Artwork tags)

- View all tags in one list
- Create, rename, and delete unused tags
- All newly created tags are General
- Tags assigned to artwork cannot be deleted until removed from those artworks

### Manual cleanup

Community Edition does **not** include merge or bulk tag tools. To consolidate duplicates (e.g. `Dogs` → `Dog`):

1. Edit each affected artwork and assign the canonical tag.
2. Saving reuses the existing normalized tag.
3. Delete the unused tag from settings when it is no longer assigned.

## Not in Community Edition

- Style / Subject / General distinction in the UI
- Tag reclassification
- **Merge tags**
- **Bulk artwork tag update**
- Typed tag columns in CSV import/export

Pro adds typed tags, reclassification, merge, and bulk-update; see Pro documentation.

## CSV

Community CSV import/export does not include tag columns. Tags are managed through the UI.
