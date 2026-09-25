<?php

namespace App\Support\ArtworkPhotoBulkImport;

use Illuminate\Support\Facades\File;

/**
 * Proves a path is inside the photo-import temporary root before delete or preview use.
 *
 * Bare string prefixes are not sufficient. realpath resolves traversal and
 * followed symlinks; containment then requires a directory-boundary prefix.
 * Recursive deletion is limited to one direct child of the root, never the root.
 */
final class PhotoImportTempContainment
{
    public static function root(): string
    {
        return storage_path('app/temp/photo-imports');
    }

    public static function resolvedRoot(): ?string
    {
        $root = self::root();

        if (! is_dir($root) || is_link($root)) {
            return null;
        }

        $resolved = realpath($root);

        return is_string($resolved) ? $resolved : null;
    }

    public static function isStrictlyInsideRoot(string $path): bool
    {
        $root = self::resolvedRoot();
        $resolved = realpath($path);

        if ($root === null || $resolved === false || $resolved === $root) {
            return false;
        }

        return str_starts_with($resolved, $root.DIRECTORY_SEPARATOR);
    }

    /**
     * Direct child directory of the photo-import root. Not the root itself.
     */
    public static function isAllowedExtractionDirectory(string $path): bool
    {
        if (! is_dir($path) || is_link($path) || ! self::isStrictlyInsideRoot($path)) {
            return false;
        }

        $root = self::resolvedRoot();
        $resolved = realpath($path);

        if ($root === null || $resolved === false) {
            return false;
        }

        $relative = substr($resolved, strlen($root) + 1);

        return $relative !== '' && ! str_contains($relative, DIRECTORY_SEPARATOR);
    }

    public static function isContainedFile(string $path): bool
    {
        if (! is_file($path) || is_link($path)) {
            return false;
        }

        return self::isStrictlyInsideRoot($path);
    }

    public static function extractionDirectoryFor(string $path): ?string
    {
        if (! self::isContainedFile($path)) {
            return null;
        }

        $root = self::resolvedRoot();
        $resolved = realpath($path);

        if ($root === null || $resolved === false) {
            return null;
        }

        $token = strtok(substr($resolved, strlen($root) + 1), DIRECTORY_SEPARATOR);

        if ($token === false || $token === '') {
            return null;
        }

        $child = $root.DIRECTORY_SEPARATOR.$token;

        return self::isAllowedExtractionDirectory($child) ? realpath($child) : null;
    }

    public static function deleteExtractionDirectory(string $path): bool
    {
        if (is_link($path)) {
            return false;
        }

        $resolved = realpath($path);

        if ($resolved === false || ! self::isAllowedExtractionDirectory($resolved)) {
            return false;
        }

        File::deleteDirectory($resolved);

        return ! is_dir($resolved);
    }

    public static function unlinkContainedFile(string $path): bool
    {
        if (is_link($path)) {
            return false;
        }

        $resolved = realpath($path);

        if ($resolved === false || ! self::isContainedFile($resolved)) {
            return false;
        }

        return unlink($resolved);
    }
}
