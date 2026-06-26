<?php

namespace App\Services;

use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use App\Support\DemoMode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArtworkPhotoService
{
    public function __construct(
        private readonly ArtworkPhotoFileHashService $fileHashService,
    ) {}

    public function store(Artwork $artwork, UploadedFile $file): ArtworkPhoto
    {
        DemoMode::ensurePhotoStorageAllowed();

        $directory = 'artworks/'.$artwork->id;
        $filename = Str::uuid()->toString().'.'.$file->guessExtension();
        $path = $file->storeAs($directory, $filename, 'public');

        [$width, $height] = $this->dimensions($file);

        $artwork->photos()->update(['is_primary' => false]);

        return $artwork->photos()->create([
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'file_hash' => $this->fileHashService->hashFile($file->getRealPath()),
            'width' => $width,
            'height' => $height,
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);
    }

    /**
     * Import a photo from an extracted ZIP path during bulk backfill.
     */
    public function importStoredFile(
        Artwork $artwork,
        string $absolutePath,
        string $originalFilename,
        ?string $caption = null,
        bool $setAsCurrent = false,
    ): ArtworkPhoto {
        DemoMode::ensurePhotoStorageAllowed();

        $directory = 'artworks/'.$artwork->id;
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION) ?: pathinfo($absolutePath, PATHINFO_EXTENSION);
        $filename = Str::uuid()->toString().($extension ? '.'.$extension : '');
        $path = $directory.'/'.$filename;

        Storage::disk('public')->put($path, file_get_contents($absolutePath));

        [$width, $height] = $this->dimensionsFromPath($absolutePath);
        $isFirstPhoto = ! $artwork->photos()->exists();

        if ($setAsCurrent) {
            $artwork->photos()->update(['is_primary' => false]);
        }

        return $artwork->photos()->create([
            'file_path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => mime_content_type($absolutePath) ?: null,
            'file_size' => filesize($absolutePath) ?: null,
            'file_hash' => $this->fileHashService->hashFile($absolutePath),
            'width' => $width,
            'height' => $height,
            'photo_type' => 'general',
            'caption' => $this->normalizeCaption($caption),
            'is_primary' => $setAsCurrent || $isFirstPhoto,
            'uploaded_at' => now(),
        ]);
    }

    public function deletePhotosForArtwork(Artwork $artwork): void
    {
        DemoMode::ensureDeletesAllowed();

        $artwork->loadMissing('photos');

        foreach ($artwork->photos as $photo) {
            Storage::disk('public')->delete($photo->file_path);
            $photo->delete();
        }
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function dimensionsFromPath(string $absolutePath): array
    {
        $size = @getimagesize($absolutePath);

        if ($size === false) {
            return [null, null];
        }

        return [$size[0], $size[1]];
    }

    private function normalizeCaption(?string $caption): ?string
    {
        if ($caption === null) {
            return null;
        }

        $caption = trim($caption);

        return $caption === '' ? null : $caption;
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function dimensions(UploadedFile $file): array
    {
        $size = @getimagesize($file->getRealPath());

        if ($size === false) {
            return [null, null];
        }

        return [$size[0], $size[1]];
    }
}
