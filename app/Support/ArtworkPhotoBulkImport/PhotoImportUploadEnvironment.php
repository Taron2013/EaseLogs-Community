<?php

namespace App\Support\ArtworkPhotoBulkImport;

final class PhotoImportUploadEnvironment
{
    /**
     * @return array{
     *     status: string,
     *     effective_max_mb: ?int,
     *     app_max_mb: int,
     *     php_post_max_mb: ?int,
     *     php_upload_max_mb: ?int,
     *     warnings: list<string>,
     *     notes: list<string>
     * }
     */
    public static function report(): array
    {
        $appMaxMb = PhotoImportUploadLimit::maxMegabytes();
        $postMaxBytes = self::iniSizeBytes('post_max_size');
        $uploadMaxBytes = self::iniSizeBytes('upload_max_filesize');
        $postMaxMb = self::bytesToMegabytes($postMaxBytes);
        $uploadMaxMb = self::bytesToMegabytes($uploadMaxBytes);
        $effectiveMaxBytes = self::effectiveMaxBytes();
        $effectiveMaxMb = self::bytesToMegabytes($effectiveMaxBytes);
        $warnings = self::warnings();
        $notes = self::notes();

        $status = 'ok';

        if ($warnings !== []) {
            $status = 'degraded';
        }

        if ($postMaxBytes === 0 || $uploadMaxBytes === 0) {
            $status = 'misconfigured';
        }

        return [
            'status' => $status,
            'effective_max_mb' => $effectiveMaxMb,
            'app_max_mb' => $appMaxMb,
            'php_post_max_mb' => $postMaxMb,
            'php_upload_max_mb' => $uploadMaxMb,
            'warnings' => $warnings,
            'notes' => $notes,
        ];
    }

    /**
     * @return array{
     *     effective_max_mb: ?int,
     *     app_max_mb: int,
     *     php_post_max_mb: ?int,
     *     php_upload_max_mb: ?int,
     *     warnings: list<string>
     * }
     */
    public static function viewData(): array
    {
        $report = self::report();

        return [
            'effective_max_mb' => $report['effective_max_mb'],
            'app_max_mb' => $report['app_max_mb'],
            'php_post_max_mb' => $report['php_post_max_mb'],
            'php_upload_max_mb' => $report['php_upload_max_mb'],
            'warnings' => $report['warnings'],
        ];
    }

    public static function effectiveMaxBytes(): ?int
    {
        $limits = array_filter([
            self::appMaxBytes(),
            self::iniSizeBytes('post_max_size'),
            self::iniSizeBytes('upload_max_filesize'),
        ], fn (?int $bytes): bool => $bytes !== null && $bytes > 0);

        if ($limits === []) {
            return null;
        }

        return min($limits);
    }

    public static function postMaxBytes(): ?int
    {
        $bytes = self::iniSizeBytes('post_max_size');

        return $bytes > 0 ? $bytes : null;
    }

    public static function uploadMaxBytes(): ?int
    {
        $bytes = self::iniSizeBytes('upload_max_filesize');

        return $bytes > 0 ? $bytes : null;
    }

    public static function appMaxBytes(): ?int
    {
        $mb = PhotoImportUploadLimit::maxMegabytes();

        return $mb > 0 ? $mb * 1024 * 1024 : null;
    }

    /**
     * @return list<string>
     */
    public static function warnings(): array
    {
        $warnings = [];
        $appBytes = self::appMaxBytes();
        $postBytes = self::postMaxBytes();
        $uploadBytes = self::uploadMaxBytes();

        if ($appBytes !== null && $postBytes !== null && $appBytes > $postBytes) {
            $warnings[] = sprintf(
                'EaseLogs allows photo ZIPs up to %s, but PHP post_max_size is only %s. Uploads larger than the PHP limit are discarded before validation runs.',
                self::formatMegabytes(self::bytesToMegabytes($appBytes)),
                self::formatMegabytes(self::bytesToMegabytes($postBytes)),
            );
        }

        if ($uploadBytes !== null && $postBytes !== null && $uploadBytes > $postBytes) {
            $warnings[] = sprintf(
                'PHP upload_max_filesize (%s) is larger than post_max_size (%s). The smaller value applies to multipart uploads.',
                self::formatMegabytes(self::bytesToMegabytes($uploadBytes)),
                self::formatMegabytes(self::bytesToMegabytes($postBytes)),
            );
        }

        if ($postBytes === null || $uploadBytes === null) {
            $warnings[] = 'PHP upload limits could not be read. Confirm PHP-FPM is using the expected php.ini values.';
        }

        return $warnings;
    }

    /**
     * @return list<string>
     */
    private static function notes(): array
    {
        return [
            'Nginx client_max_body_size is not visible to PHP. Align it with PHP and EaseLogs limits — see docs/BULK_PHOTO_IMPORT.md.',
            'This report reflects the PHP process handling the request (php-fpm for web, CLI for artisan).',
        ];
    }

    public static function uploadErrorMessage(int $errorCode, ?int $contentLength = null): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => self::uploadIniSizeMessage(),
            UPLOAD_ERR_FORM_SIZE => 'The photo ZIP exceeds the maximum size allowed by the form.',
            UPLOAD_ERR_PARTIAL => 'The photo ZIP upload was interrupted. Try again on a stable connection.',
            UPLOAD_ERR_NO_FILE => self::missingFileMessage($contentLength),
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder for uploads. Ask your administrator to set upload_tmp_dir.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded ZIP to disk. Check disk space and permissions.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the photo ZIP upload.',
            default => 'The photo ZIP could not be uploaded. Try again or use a smaller archive.',
        };
    }

    public static function missingFileMessage(?int $contentLength = null): string
    {
        $postMaxBytes = self::postMaxBytes();

        if ($contentLength !== null && $contentLength > 0 && $postMaxBytes !== null && $contentLength > $postMaxBytes) {
            return sprintf(
                'The photo ZIP was not received. The request was about %s but PHP post_max_size is only %s, so PHP discarded the upload before EaseLogs could validate it. Ask your administrator to raise post_max_size and matching Nginx client_max_body_size.',
                self::formatBytes($contentLength),
                self::formatMegabytes(self::bytesToMegabytes($postMaxBytes)),
            );
        }

        if ($contentLength !== null && $contentLength > 512 * 1024) {
            return 'The photo ZIP did not arrive on the server. The browser may have sent a large request body, but PHP did not provide a file — often caused by PHP or Nginx upload limits lower than the archive size. See docs/BULK_PHOTO_IMPORT.md.';
        }

        return 'A photo ZIP file is required.';
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return rtrim(rtrim(sprintf('%.2f', $bytes / (1024 * 1024 * 1024)), '0'), '.').' GiB';
        }

        if ($bytes >= 1024 * 1024) {
            return rtrim(rtrim(sprintf('%.2f', $bytes / (1024 * 1024)), '0'), '.').' MiB';
        }

        if ($bytes >= 1024) {
            return rtrim(rtrim(sprintf('%.2f', $bytes / 1024), '0'), '.').' KiB';
        }

        return $bytes.' bytes';
    }

    public static function formatMegabytes(?int $megabytes): string
    {
        if ($megabytes === null) {
            return 'unknown';
        }

        return number_format($megabytes).' MB';
    }

    public static function iniSizeBytes(string $directive): int
    {
        $value = ini_get($directive);

        if ($value === false || $value === '') {
            return 0;
        }

        return self::parseIniSize((string) $value);
    }

    public static function parseIniSize(string $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $value = trim($value);
        $unit = strtoupper(substr($value, -1));
        $number = (int) substr($value, 0, -1);

        return match ($unit) {
            'G' => $number * 1024 * 1024 * 1024,
            'M' => $number * 1024 * 1024,
            'K' => $number * 1024,
            default => (int) $value,
        };
    }

    private static function bytesToMegabytes(?int $bytes): ?int
    {
        if ($bytes === null || $bytes <= 0) {
            return null;
        }

        return (int) floor($bytes / (1024 * 1024));
    }

    private static function uploadIniSizeMessage(): string
    {
        $uploadMaxMb = self::bytesToMegabytes(self::uploadMaxBytes());
        $appMaxMb = PhotoImportUploadLimit::maxMegabytes();

        return sprintf(
            'The photo ZIP exceeds PHP upload_max_filesize (%s). EaseLogs allows up to %s MB in the app configuration — raise PHP and Nginx limits together if you need larger archives.',
            self::formatMegabytes($uploadMaxMb),
            number_format($appMaxMb),
        );
    }
}
