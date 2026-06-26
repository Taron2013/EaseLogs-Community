<?php

namespace App\Support\ArtworkPhotoBulkImport;

final class BulkImportRowStatus
{
    public const READY = 'ready';

    public const NEEDS_CONFIRMATION = 'needs_confirmation';

    public const PARTIAL_TITLE_MATCH = 'partial_title_match';

    public const MISSING_ARTWORK = 'missing_artwork';

    public const MISSING_PHOTO = 'missing_photo';

    public const DUPLICATE_REFERENCE = 'duplicate_reference';

    public const DUPLICATE_EXISTING_PHOTO = 'duplicate_existing_photo';

    public const INVALID_ROW = 'invalid_row';

    public const INVALID_FILE = 'invalid_file';

    public const AMBIGUOUS_MATCH = 'ambiguous_match';

    public const MANUALLY_RESOLVED = 'manually_resolved';

    public const UNMATCHED = 'unmatched';

    public const HAS_EXISTING_PHOTOS = 'has_existing_photos';
}
