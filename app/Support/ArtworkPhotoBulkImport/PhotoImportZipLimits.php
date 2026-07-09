<?php

namespace App\Support\ArtworkPhotoBulkImport;

final class PhotoImportZipLimits
{
    /** Maximum archive entries (files and directories). */
    public const DEFAULT_MAX_ENTRIES = 2000;

    /** Maximum total uncompressed payload across all entries (15 GB). */
    public const DEFAULT_MAX_TOTAL_UNCOMPRESSED_MB = 15360;

    /** Maximum uncompressed size for a single archive entry (25 MB). */
    public const DEFAULT_MAX_ENTRY_UNCOMPRESSED_MB = 25;

    /** Maximum directory nesting depth for a file path (segments). */
    public const DEFAULT_MAX_PATH_DEPTH = 10;

    /** Maximum normalized relative path length (characters). */
    public const DEFAULT_MAX_PATH_LENGTH = 255;

    public static function maxEntries(): int
    {
        return max(1, (int) config('easelogs.photo_import_zip.max_entries', self::DEFAULT_MAX_ENTRIES));
    }

    public static function maxTotalUncompressedBytes(): int
    {
        $mb = (int) config(
            'easelogs.photo_import_zip.max_total_uncompressed_mb',
            self::DEFAULT_MAX_TOTAL_UNCOMPRESSED_MB,
        );

        return max(1, $mb) * 1024 * 1024;
    }

    public static function maxEntryUncompressedBytes(): int
    {
        $mb = (int) config(
            'easelogs.photo_import_zip.max_entry_uncompressed_mb',
            self::DEFAULT_MAX_ENTRY_UNCOMPRESSED_MB,
        );

        return max(1, $mb) * 1024 * 1024;
    }

    public static function maxPathDepth(): int
    {
        return max(1, (int) config('easelogs.photo_import_zip.max_path_depth', self::DEFAULT_MAX_PATH_DEPTH));
    }

    public static function maxPathLength(): int
    {
        return max(32, (int) config('easelogs.photo_import_zip.max_path_length', self::DEFAULT_MAX_PATH_LENGTH));
    }
}
