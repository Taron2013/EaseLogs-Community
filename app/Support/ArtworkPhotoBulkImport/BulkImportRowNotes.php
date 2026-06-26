<?php

namespace App\Support\ArtworkPhotoBulkImport;

final class BulkImportRowNotes
{
    public static function forRow(array $row): string
    {
        $status = (string) ($row['status'] ?? '');
        $matchMethod = (string) ($row['match_method'] ?? '');

        return match ($status) {
            BulkImportRowStatus::READY => '✓ Ready to import',
            BulkImportRowStatus::NEEDS_CONFIRMATION => $matchMethod === 'filename_title'
                ? '✓ Filename match'
                : '✓ Exact title match',
            BulkImportRowStatus::PARTIAL_TITLE_MATCH => 'Partial title match — review before importing.',
            BulkImportRowStatus::MANUALLY_RESOLVED => 'Manual match — review before importing.',
            BulkImportRowStatus::AMBIGUOUS_MATCH => '⚠ Ambiguous title match',
            BulkImportRowStatus::MISSING_ARTWORK => '✗ No artwork found',
            BulkImportRowStatus::MISSING_PHOTO => '✗ File not in ZIP',
            BulkImportRowStatus::DUPLICATE_REFERENCE => '✗ Duplicate mapping row',
            BulkImportRowStatus::DUPLICATE_EXISTING_PHOTO => 'Exact duplicate of existing photo',
            BulkImportRowStatus::INVALID_ROW => '✗ Invalid mapping row',
            BulkImportRowStatus::INVALID_FILE => '✗ Invalid file type',
            BulkImportRowStatus::UNMATCHED => '✗ No title parsed from filename',
            default => '',
        };
    }
}
