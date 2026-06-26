<?php

namespace App\Services;

use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use App\Models\User;
use App\Support\ArtworkPhotoBulkImport\ArtworkPhotoMatcher;
use App\Support\ArtworkPhotoBulkImport\BulkImportManualResolution;
use App\Support\ArtworkPhotoBulkImport\BulkImportRowNotes;
use App\Support\ArtworkPhotoBulkImport\BulkImportRowStatus;
use App\Support\ArtworkPhotoBulkImport\FilenameTitleParser;
use App\Support\ArtworkPhotoBulkImport\PhotoImportArtworkSearch;
use App\Support\ArtworkPhotoBulkImport\PhotoImportPreviewThumbnailService;
use App\Support\DemoMode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ZipArchive;

class ArtworkPhotoBulkImportService
{
    private const CACHE_PREFIX = 'artwork_photo_bulk_preview:';

    private const MAPPING_FILENAMES = ['mapping.csv', 'manifest.csv'];

    /**
     * @var list<string>
     */
    private const PRO_ONLY_COLUMNS = ['inventory_code', 'sku'];

    /**
     * @var list<string>
     */
    private const TITLE_COLUMNS = ['title', 'artwork_title', 'name'];

    public function __construct(
        private readonly ArtworkPhotoMatcher $matcher,
        private readonly FilenameTitleParser $filenameParser,
        private readonly ArtworkPhotoService $photoService,
        private readonly PhotoImportPreviewThumbnailService $thumbnailService,
        private readonly PhotoImportArtworkSearch $artworkSearch,
        private readonly ArtworkPhotoFileHashService $fileHashService,
    ) {}

    /**
     * @return array{token: string, preview: array<string, mixed>}
     */
    public function preview(
        User $user,
        UploadedFile $zipFile,
        ?UploadedFile $mappingFile = null,
    ): array {
        DemoMode::ensureAllowed('imports');

        $token = Str::uuid()->toString();
        $extractPath = $this->extractZip($zipFile, $token);
        $mappingPath = $this->resolveMappingPath($extractPath, $mappingFile);
        $zipFiles = $this->indexZipFiles($extractPath);
        $allFiles = $this->indexAllNonCsvFiles($extractPath);
        $rows = $mappingPath !== null ? $this->parseMappingCsv($mappingPath) : [];
        $previewRows = $this->buildPreviewRows($user, $rows, $zipFiles, $allFiles);

        $preview = [
            'token' => $token,
            'extract_path' => $extractPath,
            'rows' => $previewRows,
            'summary' => $this->summarize($previewRows, count($zipFiles)),
        ];

        Cache::put(self::CACHE_PREFIX.$user->id.':'.$token, $preview, now()->addHours(2));

        return ['token' => $token, 'preview' => $preview];
    }

    /**
     * @param  list<string>  $confirmedRowKeys
     * @return array{applied: int, skipped: int, unconfirmed: int, summary: array<string, int>}
     */
    public function apply(User $user, string $token, array $confirmedRowKeys = []): array
    {
        DemoMode::ensurePhotoStorageAllowed();

        $preview = Cache::get(self::CACHE_PREFIX.$user->id.':'.$token);

        if (! is_array($preview) || ($preview['token'] ?? null) !== $token) {
            throw new \InvalidArgumentException('This photo import preview expired or is invalid. Upload again.');
        }

        $confirmed = array_fill_keys($confirmedRowKeys, true);
        $applied = 0;
        $skipped = 0;
        $unconfirmed = 0;

        foreach ($preview['rows'] as $row) {
            $status = $row['status'] ?? null;
            $rowKey = $row['row_key'] ?? null;
            $shouldImport = $status === BulkImportRowStatus::READY
                || (in_array($status, [
                    BulkImportRowStatus::NEEDS_CONFIRMATION,
                    BulkImportRowStatus::PARTIAL_TITLE_MATCH,
                    BulkImportRowStatus::MANUALLY_RESOLVED,
                ], true)
                    && is_string($rowKey) && isset($confirmed[$rowKey]));

            if (! $shouldImport) {
                $skipped++;

                if (in_array($status, [
                    BulkImportRowStatus::NEEDS_CONFIRMATION,
                    BulkImportRowStatus::PARTIAL_TITLE_MATCH,
                    BulkImportRowStatus::MANUALLY_RESOLVED,
                ], true)) {
                    $unconfirmed++;
                }

                continue;
            }

            /** @var int $artworkId */
            $artworkId = $row['artwork_id'];
            $artwork = Artwork::query()->where('user_id', $user->id)->findOrFail($artworkId);

            $this->photoService->importStoredFile(
                $artwork,
                $row['absolute_path'],
                $row['original_filename'],
                $row['caption'] ?? null,
                (bool) ($row['set_as_current'] ?? false),
            );

            $applied++;
        }

        $this->cleanupPreview($preview);

        Cache::forget(self::CACHE_PREFIX.$user->id.':'.$token);

        return [
            'applied' => $applied,
            'skipped' => $skipped,
            'unconfirmed' => $unconfirmed,
            'summary' => $preview['summary'],
        ];
    }

    public function discard(User $user, string $token): void
    {
        $preview = Cache::get(self::CACHE_PREFIX.$user->id.':'.$token);

        if (is_array($preview)) {
            $this->cleanupPreview($preview);
        }

        Cache::forget(self::CACHE_PREFIX.$user->id.':'.$token);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cachedPreview(User $user, string $token): ?array
    {
        $preview = Cache::get(self::CACHE_PREFIX.$user->id.':'.$token);

        return is_array($preview) ? $preview : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchArtworksForManualResolve(User $user, ?string $query): array
    {
        return $this->artworkSearch->search($user, $query);
    }

    /**
     * @return array{row: array<string, mixed>, summary: array<string, int>}
     */
    public function resolveRowManually(User $user, string $token, string $rowKey, int $artworkId): array
    {
        $preview = $this->requirePreview($user, $token);
        $rowIndex = $this->findRowIndex($preview['rows'], $rowKey);

        if ($rowIndex === null) {
            throw new \InvalidArgumentException('Preview row not found.');
        }

        $row = $preview['rows'][$rowIndex];

        if (! BulkImportManualResolution::canResolveRow($row)
            && ($row['status'] ?? null) !== BulkImportRowStatus::MANUALLY_RESOLVED) {
            throw new \InvalidArgumentException('This row cannot be manually resolved.');
        }

        $artwork = Artwork::query()
            ->where('user_id', $user->id)
            ->findOrFail($artworkId);

        if (($row['status'] ?? null) !== BulkImportRowStatus::MANUALLY_RESOLVED) {
            $row['manual_resolution_snapshot'] = BulkImportManualResolution::snapshotRow($row);
        }

        $preview['rows'][$rowIndex] = $this->markManuallyResolved($row, $artwork);

        return $this->persistPreview($user, $token, $preview, $rowKey);
    }

    /**
     * @return array{row: array<string, mixed>, summary: array<string, int>}
     */
    public function undoManualResolve(User $user, string $token, string $rowKey): array
    {
        $preview = $this->requirePreview($user, $token);
        $rowIndex = $this->findRowIndex($preview['rows'], $rowKey);

        if ($rowIndex === null) {
            throw new \InvalidArgumentException('Preview row not found.');
        }

        $row = $preview['rows'][$rowIndex];

        if (($row['status'] ?? null) !== BulkImportRowStatus::MANUALLY_RESOLVED) {
            throw new \InvalidArgumentException('This row does not have a manual match to undo.');
        }

        $snapshot = $row['manual_resolution_snapshot'] ?? null;

        if (! is_array($snapshot)) {
            throw new \InvalidArgumentException('Manual match snapshot is missing for this row.');
        }

        $preview['rows'][$rowIndex] = BulkImportManualResolution::restoreRowFromSnapshot($row, $snapshot);

        return $this->persistPreview($user, $token, $preview, $rowKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function requirePreview(User $user, string $token): array
    {
        $preview = Cache::get(self::CACHE_PREFIX.$user->id.':'.$token);

        if (! is_array($preview) || ($preview['token'] ?? null) !== $token) {
            throw new \InvalidArgumentException('This photo import preview expired or is invalid. Upload again.');
        }

        return $preview;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function findRowIndex(array $rows, string $rowKey): ?int
    {
        foreach ($rows as $index => $row) {
            if (($row['row_key'] ?? null) === $rowKey) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array{row: array<string, mixed>, summary: array<string, int>}
     */
    private function persistPreview(User $user, string $token, array $preview, string $rowKey): array
    {
        $preview['summary'] = $this->summarize(
            $preview['rows'],
            (int) ($preview['summary']['archive_photos'] ?? 0),
        );

        Cache::put(self::CACHE_PREFIX.$user->id.':'.$token, $preview, now()->addHours(2));

        $rowIndex = $this->findRowIndex($preview['rows'], $rowKey);
        $row = $preview['rows'][$rowIndex ?? 0] ?? [];

        return [
            'row' => $row,
            'summary' => $preview['summary'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function markManuallyResolved(array $row, Artwork $artwork): array
    {
        $row['status'] = BulkImportRowStatus::MANUALLY_RESOLVED;
        $row['artwork_id'] = $artwork->id;
        $row['artwork_title'] = $artwork->title;
        $row['match_method'] = 'manual_resolution';
        $row['has_existing_photos'] = $artwork->photos()->exists();

        if (Schema::hasColumn('artworks', 'sku')) {
            $row['matched_artwork_sku'] = $artwork->sku;
            $row['matched_artwork_inventory_code'] = $artwork->inventory_code;
        }

        $row['message'] = BulkImportRowNotes::forRow($row);

        return $row;
    }

    private function extractZip(UploadedFile $zipFile, string $token): string
    {
        $extractPath = storage_path('app/temp/photo-imports/'.$token);

        File::ensureDirectoryExists($extractPath);

        $zip = new ZipArchive;
        $opened = $zip->open($zipFile->getRealPath());

        if ($opened !== true) {
            throw new \InvalidArgumentException('The ZIP file could not be opened.');
        }

        $zip->extractTo($extractPath);
        $zip->close();

        return $extractPath;
    }

    private function resolveMappingPath(string $extractPath, ?UploadedFile $mappingFile): ?string
    {
        if ($mappingFile !== null) {
            $path = $extractPath.'/mapping-upload.csv';
            File::copy($mappingFile->getRealPath(), $path);

            return $path;
        }

        foreach (self::MAPPING_FILENAMES as $name) {
            $candidate = $extractPath.'/'.$name;

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> normalized path => absolute path
     */
    private function indexZipFiles(string $extractPath): array
    {
        $files = [];

        foreach (File::allFiles($extractPath) as $file) {
            $absolute = $file->getPathname();
            $relative = ltrim(str_replace('\\', '/', Str::after($absolute, $extractPath)), '/');

            if (Str::endsWith(strtolower($relative), '.csv')) {
                continue;
            }

            if ($this->isImageFile($relative)) {
                $files[$this->normalizeZipPath($relative)] = $absolute;
            }
        }

        return $files;
    }

    /**
     * @return array<string, string> normalized path => absolute path
     */
    private function indexAllNonCsvFiles(string $extractPath): array
    {
        $files = [];

        foreach (File::allFiles($extractPath) as $file) {
            $absolute = $file->getPathname();
            $relative = ltrim(str_replace('\\', '/', Str::after($absolute, $extractPath)), '/');

            if (Str::endsWith(strtolower($relative), '.csv')) {
                continue;
            }

            $files[$this->normalizeZipPath($relative)] = $absolute;
        }

        return $files;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseMappingCsv(string $mappingPath): array
    {
        $handle = fopen($mappingPath, 'r');

        if ($handle === false) {
            throw new \InvalidArgumentException('The mapping CSV could not be read.');
        }

        $headerRow = fgetcsv($handle);

        if ($headerRow === false) {
            fclose($handle);

            throw new \InvalidArgumentException('The mapping CSV is empty.');
        }

        $headers = $this->normalizeHeaders($headerRow);
        $this->rejectProOnlyColumns($headers);

        $rows = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if ($this->isBlankRow($row)) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                'values' => $this->mapRow($headers, $row),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Build preview rows for every ZIP photo. Mapping CSV rows override automatic filename matching.
     *
     * @param  list<array<string, mixed>>  $mappingRows
     * @param  array<string, string>  $zipFiles
     * @param  array<string, string>  $allFiles
     * @return list<array<string, mixed>>
     */
    private function buildPreviewRows(User $user, array $mappingRows, array $zipFiles, array $allFiles): array
    {
        $previewRows = [];
        $mappingByFilename = [];
        $seenCsvReferences = [];

        foreach ($mappingRows as $row) {
            $values = $row['values'];
            $filename = $this->normalizeZipPath((string) ($values['filename'] ?? ''));
            $artworkId = $this->parseArtworkId($values['artwork_id'] ?? null);
            $titleCandidate = $this->resolveTitleColumn($values);
            $referenceKey = implode('|', [
                (string) ($artworkId ?? ''),
                $titleCandidate ?? '',
                $filename,
            ]);

            if ($filename === '') {
                $preview = $this->basePreviewRow(
                    rowKey: 'line-'.$row['line'],
                    line: $row['line'],
                    filename: '',
                    values: $values,
                    source: 'mapping_csv',
                );
                $preview['status'] = BulkImportRowStatus::INVALID_ROW;
                $preview['message'] = BulkImportRowNotes::forRow($preview);
                $previewRows[] = $preview;

                continue;
            }

            if (isset($seenCsvReferences[$referenceKey])) {
                $preview = $this->basePreviewRow(
                    rowKey: 'line-'.$row['line'],
                    line: $row['line'],
                    filename: $filename,
                    values: $values,
                    source: 'mapping_csv',
                );
                $preview['status'] = BulkImportRowStatus::DUPLICATE_REFERENCE;
                $preview['message'] = BulkImportRowNotes::forRow($preview);
                $previewRows[] = $preview;

                continue;
            }

            $seenCsvReferences[$referenceKey] = true;

            if (! isset($zipFiles[$filename])) {
                $preview = $this->basePreviewRow(
                    rowKey: 'line-'.$row['line'],
                    line: $row['line'],
                    filename: $filename,
                    values: $values,
                    source: 'mapping_csv',
                );

                if (isset($allFiles[$filename]) && ! $this->isImageFile($filename)) {
                    $preview['status'] = BulkImportRowStatus::INVALID_FILE;
                } else {
                    $preview['status'] = BulkImportRowStatus::MISSING_PHOTO;
                }

                $preview['message'] = BulkImportRowNotes::forRow($preview);
                $previewRows[] = $preview;

                continue;
            }

            if (isset($mappingByFilename[$filename])) {
                $preview = $this->basePreviewRow(
                    rowKey: 'line-'.$row['line'],
                    line: $row['line'],
                    filename: $filename,
                    values: $values,
                    source: 'mapping_csv',
                );
                $preview['status'] = BulkImportRowStatus::DUPLICATE_REFERENCE;
                $preview['message'] = BulkImportRowNotes::forRow($preview);
                $previewRows[] = $preview;

                continue;
            }

            $mappingByFilename[$filename] = $row;
        }

        $existingPhotoHashes = $this->fileHashService->hashIndexForUser($user);

        foreach ($zipFiles as $filename => $absolutePath) {
            if (! $this->isImageFile($filename)) {
                $preview = $this->basePreviewRow(
                    rowKey: 'file-'.sha1($filename),
                    line: null,
                    filename: $filename,
                    values: [],
                    source: 'zip_filename',
                );
                $preview['status'] = BulkImportRowStatus::INVALID_FILE;
                $preview['message'] = BulkImportRowNotes::forRow($preview);
                $previewRows[] = $preview;

                continue;
            }

            if (isset($mappingByFilename[$filename])) {
                $row = $mappingByFilename[$filename];
                $values = $row['values'];
                $preview = $this->basePreviewRow(
                    rowKey: 'line-'.$row['line'],
                    line: $row['line'],
                    filename: $filename,
                    values: $values,
                    source: 'mapping_csv',
                );
                $preview['absolute_path'] = $absolutePath;
                $preview = $this->detectDuplicatePhoto($preview, $absolutePath, $existingPhotoHashes);

                if (($preview['status'] ?? null) !== BulkImportRowStatus::DUPLICATE_EXISTING_PHOTO) {
                    $preview = $this->resolveArtworkMatch(
                        $user,
                        $preview,
                        $this->parseArtworkId($values['artwork_id'] ?? null),
                        $this->resolveTitleColumn($values),
                        $filename,
                    );
                }
            } else {
                $preview = $this->basePreviewRow(
                    rowKey: 'file-'.sha1($filename),
                    line: null,
                    filename: $filename,
                    values: [],
                    source: 'zip_filename',
                );
                $preview['absolute_path'] = $absolutePath;
                $preview = $this->detectDuplicatePhoto($preview, $absolutePath, $existingPhotoHashes);

                if (($preview['status'] ?? null) !== BulkImportRowStatus::DUPLICATE_EXISTING_PHOTO) {
                    $parsedTitle = $this->filenameParser->parse($filename);
                    $preview = $this->resolveArtworkMatch(
                        $user,
                        $preview,
                        null,
                        $parsedTitle,
                        $filename,
                        filenameDerived: true,
                    );
                }
            }

            $previewRows[] = $preview;
        }

        usort($previewRows, function (array $left, array $right): int {
            return strcmp((string) ($left['filename'] ?? ''), (string) ($right['filename'] ?? ''));
        });

        return $previewRows;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function basePreviewRow(
        string $rowKey,
        ?int $line,
        string $filename,
        array $values,
        string $source,
    ): array {
        return [
            'row_key' => $rowKey,
            'line' => $line,
            'source' => $source,
            'artwork_id' => null,
            'title_candidate' => null,
            'filename' => $filename,
            'caption' => $values['caption'] ?? null,
            'set_as_current' => $this->parseBoolean($values['set_as_current'] ?? null),
            'status' => BulkImportRowStatus::READY,
            'message' => null,
            'match_method' => null,
            'artwork_title' => null,
            'has_existing_photos' => false,
            'absolute_path' => null,
            'original_filename' => basename($filename),
            'file_hash' => null,
            'duplicate_photo_id' => null,
        ];
    }

    /**
     * @param  array<string, ArtworkPhoto>  $existingPhotoHashes
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    private function detectDuplicatePhoto(array $preview, string $absolutePath, array $existingPhotoHashes): array
    {
        $hash = $this->fileHashService->hashFile($absolutePath);
        $preview['file_hash'] = $hash;

        $existingPhoto = $existingPhotoHashes[$hash] ?? null;

        if ($existingPhoto === null) {
            return $preview;
        }

        return $this->markDuplicateExistingPhoto($preview, $existingPhoto);
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    private function markDuplicateExistingPhoto(array $preview, ArtworkPhoto $existingPhoto): array
    {
        $artwork = $existingPhoto->artwork;

        $preview['status'] = BulkImportRowStatus::DUPLICATE_EXISTING_PHOTO;
        $preview['match_method'] = 'duplicate_hash';
        $preview['duplicate_photo_id'] = $existingPhoto->id;
        $preview['artwork_id'] = $artwork?->id;
        $preview['artwork_title'] = $artwork?->title;
        $preview['has_existing_photos'] = true;
        $preview['message'] = BulkImportRowNotes::forRow($preview);

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    private function resolveArtworkMatch(
        User $user,
        array $preview,
        ?int $artworkId,
        ?string $titleCandidate,
        string $filename,
        bool $filenameDerived = false,
    ): array {
        if ($artworkId !== null) {
            $match = $this->matcher->matchByArtworkId($user, $artworkId);

            if ($match['artwork'] === null) {
                $preview['status'] = BulkImportRowStatus::MISSING_ARTWORK;
                $preview['message'] = BulkImportRowNotes::forRow($preview);

                return $preview;
            }

            return $this->markReadyMatch($preview, $match['artwork'], $match['method']);
        }

        if ($titleCandidate === null) {
            $titleCandidate = $this->filenameParser->parse($filename);
            $filenameDerived = $titleCandidate !== null;
        }

        if ($titleCandidate === null) {
            $preview['status'] = BulkImportRowStatus::UNMATCHED;
            $preview['message'] = BulkImportRowNotes::forRow($preview);

            return $preview;
        }

        $preview['title_candidate'] = $titleCandidate;
        $match = $this->matcher->matchByTitleCandidate($user, $titleCandidate);

        if ($match['ambiguous']) {
            $preview['status'] = BulkImportRowStatus::AMBIGUOUS_MATCH;
            $preview['match_method'] = $filenameDerived ? 'filename_title' : 'title_candidate';
            $preview['message'] = BulkImportRowNotes::forRow($preview);

            return $preview;
        }

        if ($match['artwork'] === null) {
            return $this->resolvePartialTitleMatch(
                $user,
                $preview,
                $titleCandidate,
                $filenameDerived ? 'filename_title' : 'title_candidate',
            );
        }

        $preview['status'] = BulkImportRowStatus::NEEDS_CONFIRMATION;
        $preview['artwork_id'] = $match['artwork']->id;
        $preview['artwork_title'] = $match['artwork']->title;
        $preview['match_method'] = $filenameDerived ? 'filename_title' : 'title_candidate';
        $preview['has_existing_photos'] = $match['artwork']->photos()->exists();
        $preview['message'] = BulkImportRowNotes::forRow($preview);

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    private function resolvePartialTitleMatch(
        User $user,
        array $preview,
        string $titleCandidate,
        string $sourceMethod,
    ): array {
        $partialMatch = $this->matcher->matchByPartialTitleCandidate($user, $titleCandidate);

        if ($partialMatch['ambiguous']) {
            $preview['status'] = BulkImportRowStatus::AMBIGUOUS_MATCH;
            $preview['match_method'] = 'partial_title_match';
            $preview['message'] = BulkImportRowNotes::forRow($preview);

            return $preview;
        }

        if ($partialMatch['artwork'] === null) {
            $preview['status'] = BulkImportRowStatus::MISSING_ARTWORK;
            $preview['match_method'] = $sourceMethod;
            $preview['message'] = BulkImportRowNotes::forRow($preview);

            return $preview;
        }

        return $this->markPartialTitleMatch($preview, $partialMatch['artwork'], [
            'title_candidate' => $titleCandidate,
            'match_confidence' => $partialMatch['confidence'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function markPartialTitleMatch(array $preview, Artwork $artwork, array $extra = []): array
    {
        $preview['status'] = BulkImportRowStatus::PARTIAL_TITLE_MATCH;
        $preview['artwork_id'] = $artwork->id;
        $preview['artwork_title'] = $artwork->title;
        $preview['match_method'] = 'partial_title_match';
        $preview['has_existing_photos'] = $artwork->photos()->exists();
        $preview['message'] = BulkImportRowNotes::forRow($preview);

        return array_merge($preview, $extra);
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    private function markReadyMatch(array $preview, Artwork $artwork, ?string $method): array
    {
        $preview['status'] = BulkImportRowStatus::READY;
        $preview['artwork_id'] = $artwork->id;
        $preview['artwork_title'] = $artwork->title;
        $preview['match_method'] = $method;
        $preview['has_existing_photos'] = $artwork->photos()->exists();
        $preview['message'] = BulkImportRowNotes::forRow($preview);

        return $preview;
    }

    /**
     * @param  array<string, ?string>  $values
     */
    private function resolveTitleColumn(array $values): ?string
    {
        foreach (self::TITLE_COLUMNS as $column) {
            $value = trim((string) ($values[$column] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function summarize(array $rows, int $archivePhotos): array
    {
        $summary = [
            'archive_photos' => $archivePhotos,
            'automatic_matches' => 0,
            'title_matches_awaiting_confirmation' => 0,
            'ambiguous_matches' => 0,
            'unmatched_photos' => 0,
            'photos_will_skip' => 0,
            'photos_ready_to_import' => 0,
            'matched_photos' => 0,
            'needs_review' => 0,
            'total' => count($rows),
            'ready' => 0,
            'needs_confirmation' => 0,
            'partial_title_match' => 0,
            'missing_artwork' => 0,
            'missing_photo' => 0,
            'duplicate_reference' => 0,
            'duplicate_existing_photo' => 0,
            'invalid_row' => 0,
            'invalid_file' => 0,
            'ambiguous_match' => 0,
            'manually_resolved' => 0,
            'unmatched' => 0,
            'has_existing_photos' => 0,
        ];

        foreach ($rows as $row) {
            $status = $row['status'];
            $matchMethod = (string) ($row['match_method'] ?? '');

            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }

            if ($status === BulkImportRowStatus::READY) {
                $summary['photos_ready_to_import']++;
                $summary['matched_photos']++;
            }

            if ($status === BulkImportRowStatus::NEEDS_CONFIRMATION) {
                $summary['matched_photos']++;
                $summary['needs_review']++;

                if ($matchMethod === 'filename_title') {
                    $summary['automatic_matches']++;
                } else {
                    $summary['title_matches_awaiting_confirmation']++;
                }
            }

            if ($status === BulkImportRowStatus::PARTIAL_TITLE_MATCH) {
                $summary['matched_photos']++;
                $summary['needs_review']++;
            }

            if ($status === BulkImportRowStatus::MANUALLY_RESOLVED) {
                $summary['matched_photos']++;
                $summary['needs_review']++;
            }

            if ($status === BulkImportRowStatus::AMBIGUOUS_MATCH) {
                $summary['ambiguous_matches']++;
                $summary['photos_will_skip']++;
            } elseif ($status === BulkImportRowStatus::UNMATCHED) {
                $summary['unmatched_photos']++;
                $summary['photos_will_skip']++;
            } elseif ($status === BulkImportRowStatus::MISSING_ARTWORK) {
                $summary['unmatched_photos']++;
                $summary['photos_will_skip']++;
            } elseif (in_array($status, [
                BulkImportRowStatus::MISSING_PHOTO,
                BulkImportRowStatus::DUPLICATE_REFERENCE,
                BulkImportRowStatus::DUPLICATE_EXISTING_PHOTO,
                BulkImportRowStatus::INVALID_ROW,
                BulkImportRowStatus::INVALID_FILE,
            ], true)) {
                $summary['photos_will_skip']++;
            }

            if ($row['has_existing_photos'] ?? false) {
                $summary['has_existing_photos']++;
            }
        }

        return $summary;
    }

    /**
     * @param  list<string>  $headerRow
     * @return list<string>
     */
    private function normalizeHeaders(array $headerRow): array
    {
        return array_map(
            fn (?string $header): string => strtolower(trim((string) $header)),
            $headerRow,
        );
    }

    /**
     * @param  list<string>  $headers
     */
    private function rejectProOnlyColumns(array $headers): void
    {
        foreach (self::PRO_ONLY_COLUMNS as $column) {
            if (in_array($column, $headers, true)) {
                throw new \InvalidArgumentException(
                    'Column "'.$column.'" is a Pro-only field and is not supported in Community Edition.',
                );
            }
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $row
     * @return array<string, ?string>
     */
    private function mapRow(array $headers, array $row): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $mapped[$header] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        return $mapped;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseBoolean(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'y'], true);
    }

    private function parseArtworkId(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeZipPath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    private function isImageFile(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, config('easelogs.photo_mimes', ['jpeg', 'jpg', 'png', 'webp']), true);
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function cleanupPreview(array $preview): void
    {
        $extractPath = $preview['extract_path'] ?? null;

        if (is_string($extractPath) && str_starts_with($extractPath, storage_path('app/temp/photo-imports'))) {
            $this->thumbnailService->cleanupExtractDirectory($extractPath);
            File::deleteDirectory($extractPath);
        }
    }
}
