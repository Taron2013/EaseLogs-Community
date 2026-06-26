<?php

namespace App\Services;

use App\Models\ArtworkPhoto;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ArtworkPhotoFileHashService
{
    public function hashFile(string $absolutePath): string
    {
        $hash = hash_file('sha256', $absolutePath);

        if ($hash === false) {
            throw new \RuntimeException('Could not hash file: '.$absolutePath);
        }

        return $hash;
    }

    public function hashContents(string $contents): string
    {
        return hash('sha256', $contents);
    }

    /**
     * @return array<string, ArtworkPhoto>
     */
    public function hashIndexForUser(User $user): array
    {
        $photos = ArtworkPhoto::query()
            ->whereHas('artwork', fn ($query) => $query->where('user_id', $user->id))
            ->with('artwork')
            ->get();

        $index = [];

        foreach ($photos as $photo) {
            $hash = $this->ensureStoredHash($photo);

            if ($hash === null) {
                continue;
            }

            $index[$hash] ??= $photo;
        }

        return $index;
    }

    public function findExistingPhotoByHash(User $user, string $hash): ?ArtworkPhoto
    {
        return $this->hashIndexForUser($user)[$hash] ?? null;
    }

    public function ensureStoredHash(ArtworkPhoto $photo): ?string
    {
        if (is_string($photo->file_hash) && $photo->file_hash !== '') {
            return $photo->file_hash;
        }

        if (! $photo->existsOnDisk()) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($photo->file_path);
        $hash = $this->hashFile($absolutePath);

        $photo->forceFill(['file_hash' => $hash])->save();

        return $hash;
    }
}
