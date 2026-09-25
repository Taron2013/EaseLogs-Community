<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Intrinsic artwork-image qualification (Track B1 / B1.1).
 *
 * Establishes: readable file, encoded-byte limit (photo_max_kb), supported
 * actual image type from file content, and positive dimensions. Extension and
 * client MIME are never sufficient. Failure of these checks is rejection.
 *
 * Does not decode the compressed payload, prove malware-free or trusted
 * content, or impose a pixel/dimension ceiling. Full decode belongs to a later
 * isolated canonicalization boundary, not this admission gate.
 */
final class ArtworkImageQualifier
{
    /**
     * @var array<string, 'jpeg'|'png'|'webp'>
     */
    private const MIME_TO_FORMAT = [
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @var array<'jpeg'|'png'|'webp', string>
     */
    private const FORMAT_TO_MIME = [
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function qualifyUploadedFile(UploadedFile $file): AcceptedArtworkImage
    {
        $path = $file->getRealPath();

        if ($path === false || $path === '') {
            throw new ArtworkImageRejectedException(
                ArtworkImageRejectedException::REASON_UNREADABLE,
            );
        }

        return $this->qualifyPath($path);
    }

    public function qualifyPath(string $absolutePath): AcceptedArtworkImage
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new ArtworkImageRejectedException(
                ArtworkImageRejectedException::REASON_UNREADABLE,
            );
        }

        $sizeBytes = filesize($absolutePath);

        if ($sizeBytes === false || $sizeBytes < 1) {
            throw new ArtworkImageRejectedException(
                ArtworkImageRejectedException::REASON_UNREADABLE,
            );
        }

        $maxKb = (int) config('easelogs.photo_max_kb', 10240);

        if ($maxKb > 0 && $sizeBytes > ($maxKb * 1024)) {
            throw new ArtworkImageRejectedException(
                ArtworkImageRejectedException::REASON_OVERSIZE,
            );
        }

        $info = @getimagesize($absolutePath);

        if ($info === false || ! isset($info['mime']) || ! is_string($info['mime'])) {
            throw new ArtworkImageRejectedException(
                ArtworkImageRejectedException::REASON_UNSUPPORTED_TYPE,
            );
        }

        $format = self::MIME_TO_FORMAT[$info['mime']] ?? null;

        if ($format === null) {
            throw new ArtworkImageRejectedException(
                ArtworkImageRejectedException::REASON_UNSUPPORTED_TYPE,
            );
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);

        if ($width < 1 || $height < 1) {
            throw new ArtworkImageRejectedException(
                ArtworkImageRejectedException::REASON_INVALID_DIMENSIONS,
            );
        }

        return new AcceptedArtworkImage(
            format: $format,
            mimeType: self::FORMAT_TO_MIME[$format],
            width: $width,
            height: $height,
            sizeBytes: $sizeBytes,
        );
    }
}
