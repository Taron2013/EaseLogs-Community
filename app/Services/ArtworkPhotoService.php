<?php

namespace App\Services;

use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use App\Support\ArtworkImageQualifier;
use App\Support\DemoMode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArtworkPhotoService
{
    public function __construct(
        private readonly ArtworkPhotoFileHashService $fileHashService,
        private readonly ArtworkImageQualifier $imageQualifier,
    ) {}

    public function store(Artwork $artwork, UploadedFile $file): ArtworkPhoto
    {
        DemoMode::ensurePhotoStorageAllowed();

        $accepted = $this->imageQualifier->qualifyUploadedFile($file);

        $directory = 'artworks/'.$artwork->id;
        $extension = $accepted->format === 'jpeg' ? 'jpg' : $accepted->format;
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $filename, 'public');

        $artwork->photos()->update(['is_primary' => false]);

        return $artwork->photos()->create([
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $accepted->mimeType,
            'file_size' => $accepted->sizeBytes,
            'file_hash' => $this->fileHashService->hashFile($file->getRealPath()),
            'width' => $accepted->width,
            'height' => $accepted->height,
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

        $accepted = $this->imageQualifier->qualifyPath($absolutePath);

        $directory = 'artworks/'.$artwork->id;
        $extension = $accepted->format === 'jpeg' ? 'jpg' : $accepted->format;
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $directory.'/'.$filename;

        Storage::disk('public')->put($path, file_get_contents($absolutePath));

        $isFirstPhoto = ! $artwork->photos()->exists();

        if ($setAsCurrent) {
            $artwork->photos()->update(['is_primary' => false]);
        }

        return $artwork->photos()->create([
            'file_path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => $accepted->mimeType,
            'file_size' => $accepted->sizeBytes,
            'file_hash' => $this->fileHashService->hashFile($absolutePath),
            'width' => $accepted->width,
            'height' => $accepted->height,
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

    private function normalizeCaption(?string $caption): ?string
    {
        if ($caption === null) {
            return null;
        }

        $caption = trim($caption);

        return $caption === '' ? null : $caption;
    }
}
