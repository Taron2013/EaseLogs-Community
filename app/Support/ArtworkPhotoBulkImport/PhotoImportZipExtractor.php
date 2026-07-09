<?php

namespace App\Support\ArtworkPhotoBulkImport;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use ZipArchive;

final class PhotoImportZipExtractor
{
    /**
     * Validate and extract a photo import ZIP into a fresh directory.
     *
     * @throws InvalidArgumentException
     */
    public function extract(string $zipPath, string $extractPath): void
    {
        File::ensureDirectoryExists($extractPath);

        $zip = new ZipArchive;
        $opened = $zip->open($zipPath);

        if ($opened !== true) {
            $this->cleanupDirectory($extractPath);

            throw new InvalidArgumentException('The ZIP file could not be opened.');
        }

        try {
            $entries = $this->validateArchive($zip);
            $this->extractValidatedEntries($zip, $extractPath, $entries);
        } catch (InvalidArgumentException $exception) {
            $this->cleanupDirectory($extractPath);

            throw $exception;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string> validated archive entry names in extraction order
     */
    private function validateArchive(ZipArchive $zip): array
    {
        $entryCount = $zip->numFiles;

        if ($entryCount > PhotoImportZipLimits::maxEntries()) {
            throw new InvalidArgumentException(sprintf(
                'The ZIP archive contains too many entries (limit: %s).',
                number_format(PhotoImportZipLimits::maxEntries()),
            ));
        }

        $validatedNames = [];
        $normalizedPaths = [];
        $totalUncompressedBytes = 0;

        for ($index = 0; $index < $entryCount; $index++) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name) || $name === '') {
                throw new InvalidArgumentException('The ZIP archive contains invalid file paths.');
            }

            if ($this->isSymlinkEntry($zip, $index)) {
                throw new InvalidArgumentException('The ZIP archive contains unsupported symbolic link entries.');
            }

            $normalizedPath = $this->normalizeAndValidatePath($name);
            $isDirectory = str_ends_with($name, '/') || str_ends_with($name, '\\');

            if ($normalizedPath === '' && $isDirectory) {
                continue;
            }

            if ($normalizedPath === '') {
                throw new InvalidArgumentException('The ZIP archive contains unsafe file paths and cannot be imported.');
            }

            if (isset($normalizedPaths[$normalizedPath])) {
                throw new InvalidArgumentException('The ZIP archive contains duplicate file paths.');
            }

            $normalizedPaths[$normalizedPath] = true;

            if (! $isDirectory) {
                $stat = $zip->statIndex($index);

                if ($stat === false) {
                    throw new InvalidArgumentException('The ZIP archive could not be read.');
                }

                $entrySize = (int) ($stat['size'] ?? 0);

                if ($entrySize > PhotoImportZipLimits::maxEntryUncompressedBytes()) {
                    throw new InvalidArgumentException(sprintf(
                        'The ZIP archive contains a file that is too large: %s (limit: %s MB).',
                        $normalizedPath,
                        number_format((int) config(
                            'easelogs.photo_import_zip.max_entry_uncompressed_mb',
                            PhotoImportZipLimits::DEFAULT_MAX_ENTRY_UNCOMPRESSED_MB,
                        )),
                    ));
                }

                $totalUncompressedBytes += $entrySize;

                if ($totalUncompressedBytes > PhotoImportZipLimits::maxTotalUncompressedBytes()) {
                    throw new InvalidArgumentException(sprintf(
                        'The ZIP archive is too large when uncompressed (limit: %s MB).',
                        number_format((int) config(
                            'easelogs.photo_import_zip.max_total_uncompressed_mb',
                            PhotoImportZipLimits::DEFAULT_MAX_TOTAL_UNCOMPRESSED_MB,
                        )),
                    ));
                }
            }

            $validatedNames[] = $name;
        }

        return $validatedNames;
    }

    /**
     * @param  list<string>  $entryNames
     */
    private function extractValidatedEntries(ZipArchive $zip, string $extractPath, array $entryNames): void
    {
        foreach ($entryNames as $entryName) {
            if (! $zip->extractTo($extractPath, [$entryName])) {
                throw new InvalidArgumentException('The ZIP archive could not be extracted safely.');
            }
        }
    }

    private function normalizeAndValidatePath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('The ZIP archive contains unsafe file paths and cannot be imported.');
        }

        $normalizedSlashes = str_replace('\\', '/', $path);

        if (str_starts_with($normalizedSlashes, '/')) {
            throw new InvalidArgumentException('The ZIP archive contains unsafe file paths and cannot be imported.');
        }

        if (preg_match('/^[a-zA-Z]:\\//', $normalizedSlashes) === 1) {
            throw new InvalidArgumentException('The ZIP archive contains unsafe file paths and cannot be imported.');
        }

        $trimmed = rtrim($normalizedSlashes, '/');
        $segments = explode('/', $trimmed);
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new InvalidArgumentException('The ZIP archive contains unsafe file paths and cannot be imported.');
            }

            $resolved[] = $segment;
        }

        $normalized = implode('/', $resolved);

        if ($normalized !== '' && strlen($normalized) > PhotoImportZipLimits::maxPathLength()) {
            throw new InvalidArgumentException(sprintf(
                'The ZIP archive contains file paths that are too long (limit: %s characters).',
                number_format(PhotoImportZipLimits::maxPathLength()),
            ));
        }

        if ($normalized !== '' && count($resolved) > PhotoImportZipLimits::maxPathDepth()) {
            throw new InvalidArgumentException(sprintf(
                'The ZIP archive contains paths that are nested too deeply (limit: %s levels).',
                number_format(PhotoImportZipLimits::maxPathDepth()),
            ));
        }

        return $normalized;
    }

    private function isSymlinkEntry(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attrs = 0;

        if (! $zip->getExternalAttributesIndex($index, $opsys, $attrs)) {
            return false;
        }

        if ($opsys !== ZipArchive::OPSYS_UNIX) {
            return false;
        }

        $mode = ($attrs >> 16) & 0xFFFF;

        return ($mode & 0170000) === 0120000;
    }

    private function cleanupDirectory(string $extractPath): void
    {
        if (is_dir($extractPath)) {
            File::deleteDirectory($extractPath);
        }
    }
}
