<?php

namespace App\Support\PersistentFileStorage;

/**
 * Minimal provider capability report.
 *
 * Callers inspect capabilities instead of branching on driver names.
 */
final readonly class ProviderCapabilities
{
    public function __construct(
        public bool $streamingReads = true,
        public bool $temporaryUrls = false,
        public bool $checksums = true,
        public bool $objectMetadata = true,
        public bool $serverSideCopy = true,
        public bool $rangeRequests = false,
    ) {}

    /**
     * @return array{
     *     streaming_reads: bool,
     *     temporary_urls: bool,
     *     checksums: bool,
     *     object_metadata: bool,
     *     server_side_copy: bool,
     *     range_requests: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'streaming_reads' => $this->streamingReads,
            'temporary_urls' => $this->temporaryUrls,
            'checksums' => $this->checksums,
            'object_metadata' => $this->objectMetadata,
            'server_side_copy' => $this->serverSideCopy,
            'range_requests' => $this->rangeRequests,
        ];
    }
}
