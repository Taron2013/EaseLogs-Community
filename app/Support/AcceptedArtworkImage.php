<?php

namespace App\Support;

/**
 * Result of successful intrinsic artwork-image qualification.
 *
 * Proves the bounded admission checks only: supported actual type, positive
 * dimensions, and encoded-size compliance. It does not prove a full decode,
 * malware-free content, or trust.
 */
final readonly class AcceptedArtworkImage
{
    /**
     * @param  'jpeg'|'png'|'webp'  $format
     */
    public function __construct(
        public string $format,
        public string $mimeType,
        public int $width,
        public int $height,
        public int $sizeBytes,
    ) {}
}
