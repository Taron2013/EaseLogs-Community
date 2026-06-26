<?php

namespace App\Support\ArtworkPhotoBulkImport;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class PhotoImportPreviewThumbnailService
{
    private const CACHE_PREFIX = 'artwork_photo_bulk_preview:';

    private const THUMB_SUBDIR = '.thumbs';

    private const MAX_WIDTH = 120;

    public function respond(User $user, string $token, string $rowKey): Response
    {
        $absolutePath = $this->resolveSourcePath($user, $token, $rowKey);

        if ($absolutePath === null) {
            return $this->placeholderResponse();
        }

        $thumbPath = $this->thumbnailPath($absolutePath, $rowKey);

        if ($thumbPath === null || ! $this->ensureThumbnail($absolutePath, $thumbPath)) {
            return $this->placeholderResponse();
        }

        return response()->file($thumbPath, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function cleanupExtractDirectory(string $extractPath): void
    {
        $thumbDir = $extractPath.'/'.self::THUMB_SUBDIR;

        if (is_dir($thumbDir)) {
            foreach (glob($thumbDir.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($thumbDir);
        }
    }

    private function resolveSourcePath(User $user, string $token, string $rowKey): ?string
    {
        $preview = Cache::get(self::CACHE_PREFIX.$user->id.':'.$token);

        if (! is_array($preview) || ($preview['token'] ?? null) !== $token) {
            return null;
        }

        foreach ($preview['rows'] as $row) {
            if (($row['row_key'] ?? null) === $rowKey) {
                $path = $row['absolute_path'] ?? null;

                return is_string($path) && is_file($path) ? $path : null;
            }
        }

        return null;
    }

    private function thumbnailPath(string $absolutePath, string $rowKey): ?string
    {
        $extractPath = $this->extractRoot($absolutePath);

        if ($extractPath === null) {
            return null;
        }

        $thumbDir = $extractPath.'/'.self::THUMB_SUBDIR;

        if (! is_dir($thumbDir) && ! mkdir($thumbDir, 0755, true) && ! is_dir($thumbDir)) {
            return null;
        }

        return $thumbDir.'/'.$this->safeThumbFilename($rowKey).'.jpg';
    }

    private function extractRoot(string $absolutePath): ?string
    {
        $marker = 'photo-imports'.DIRECTORY_SEPARATOR;

        if (! str_contains($absolutePath, $marker)) {
            return null;
        }

        $relative = Str::after($absolutePath, $marker);
        $token = strtok($relative, DIRECTORY_SEPARATOR);

        if ($token === false || $token === '') {
            return null;
        }

        $root = storage_path('app/temp/photo-imports/'.$token);
        $realSource = realpath($absolutePath);
        $realRoot = realpath($root);

        if ($realSource === false || $realRoot === false || ! str_starts_with($realSource, $realRoot)) {
            return null;
        }

        return $realRoot;
    }

    private function safeThumbFilename(string $rowKey): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $rowKey) ?: 'row';
    }

    private function ensureThumbnail(string $source, string $destination): bool
    {
        if (is_file($destination) && filemtime($destination) >= filemtime($source)) {
            return true;
        }

        $image = $this->loadImage($source);

        if ($image === null) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);

            return false;
        }

        $targetWidth = min(self::MAX_WIDTH, $width);
        $targetHeight = (int) max(1, round($height * ($targetWidth / $width)));

        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($thumbnail === false) {
            imagedestroy($image);

            return false;
        }

        imagecopyresampled(
            $thumbnail,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height,
        );

        imagedestroy($image);

        $saved = imagejpeg($thumbnail, $destination, 82);
        imagedestroy($thumbnail);

        return $saved;
    }

    /**
     * @return resource|null
     */
    private function loadImage(string $source)
    {
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($source),
            'png' => @imagecreatefrompng($source),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : null,
            default => @imagecreatefromstring((string) file_get_contents($source)),
        };
    }

    private function placeholderResponse(): Response
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="120" height="90" viewBox="0 0 120 90">
  <rect width="120" height="90" fill="#ececeb"/>
  <text x="60" y="48" text-anchor="middle" font-family="system-ui,sans-serif" font-size="11" fill="#777">No preview</text>
</svg>
SVG;

        return new Response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store',
        ]);
    }
}
