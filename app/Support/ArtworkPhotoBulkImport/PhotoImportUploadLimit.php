<?php

namespace App\Support\ArtworkPhotoBulkImport;

final class PhotoImportUploadLimit
{
    public const DEFAULT_MB = 4096;

    public static function maxMegabytes(): int
    {
        $mb = (int) config('easelogs.photo_import_max_upload_mb', self::DEFAULT_MB);

        return $mb > 0 ? $mb : self::DEFAULT_MB;
    }

    public static function maxKilobytes(): int
    {
        return self::maxMegabytes() * 1024;
    }

    public static function appliesLaravelMaxRule(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public static function photoZipRules(): array
    {
        return [
            'required',
            'file',
            'mimes:zip',
            'max:'.self::maxKilobytes(),
        ];
    }
}
