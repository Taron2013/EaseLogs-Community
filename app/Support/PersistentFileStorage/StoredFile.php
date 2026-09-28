<?php

namespace App\Support\PersistentFileStorage;

/**
 * Metadata snapshot for a stored object after write or inspection.
 *
 * Does not embed Laravel disk names, absolute paths, or provider SDK handles.
 */
final readonly class StoredFile
{
    public function __construct(
        public StorageKey $storageKey,
        public ?string $originalFilename = null,
        public ?string $mimeType = null,
        public ?int $sizeBytes = null,
        public ?string $sha256 = null,
        public ?string $variant = null,
    ) {}
}
