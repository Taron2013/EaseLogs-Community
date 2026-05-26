<?php

namespace App\Services;

use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArtworkPhotoService
{
    public function store(Artwork $artwork, UploadedFile $file): ArtworkPhoto
    {
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
            'width' => $width,
            'height' => $height,
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);
    }

    public function deletePhotosForArtwork(Artwork $artwork): void
    {
        $artwork->loadMissing('photos');

        foreach ($artwork->photos as $photo) {
            Storage::disk('public')->delete($photo->file_path);
            $photo->delete();
        }
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
