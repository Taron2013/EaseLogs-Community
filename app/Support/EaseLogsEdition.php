<?php

namespace App\Support;

final class EaseLogsEdition
{
    public static function supportsArtworkTagAdmin(): bool
    {
        return true;
    }

    public static function supportsArtworkTagMerge(): bool
    {
        return false;
    }

    public static function supportsBulkUpdates(): bool
    {
        return false;
    }

    public static function usesSimplifiedArtworkTags(): bool
    {
        return true;
    }

    public static function enforcesGeneralOnlyArtworkTags(): bool
    {
        return true;
    }
}
