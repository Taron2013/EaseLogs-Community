<?php

namespace App\Support\ArtworkPhotoBulkImport;

final class BulkImportManualResolution
{
    /**
     * @var list<string>
     */
    public const RESOLVABLE_STATUSES = [
        BulkImportRowStatus::UNMATCHED,
        BulkImportRowStatus::MISSING_ARTWORK,
        BulkImportRowStatus::AMBIGUOUS_MATCH,
        BulkImportRowStatus::PARTIAL_TITLE_MATCH,
        BulkImportRowStatus::MANUALLY_RESOLVED,
    ];

    public static function isResolvableStatus(string $status): bool
    {
        return in_array($status, self::RESOLVABLE_STATUSES, true);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function canResolveRow(array $row): bool
    {
        if (empty($row['absolute_path']) || ! is_string($row['absolute_path'])) {
            return false;
        }

        return self::isResolvableStatus((string) ($row['status'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function snapshotRow(array $row): array
    {
        return [
            'status' => $row['status'] ?? null,
            'artwork_id' => $row['artwork_id'] ?? null,
            'artwork_title' => $row['artwork_title'] ?? null,
            'match_method' => $row['match_method'] ?? null,
            'message' => $row['message'] ?? null,
            'matched_artwork_sku' => $row['matched_artwork_sku'] ?? null,
            'matched_artwork_inventory_code' => $row['matched_artwork_inventory_code'] ?? null,
            'match_confidence' => $row['match_confidence'] ?? null,
            'conflicting_title_artwork_id' => $row['conflicting_title_artwork_id'] ?? null,
            'conflicting_title_artwork_title' => $row['conflicting_title_artwork_title'] ?? null,
            'has_existing_photos' => $row['has_existing_photos'] ?? false,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function restoreRowFromSnapshot(array $row, array $snapshot): array
    {
        foreach ($snapshot as $key => $value) {
            $row[$key] = $value;
        }

        unset($row['manual_resolution_snapshot']);

        return $row;
    }
}
