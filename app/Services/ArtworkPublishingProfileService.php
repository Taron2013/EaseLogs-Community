<?php

namespace App\Services;

use App\Models\Artwork;
use App\Models\ArtworkPublishingProfile;

class ArtworkPublishingProfileService
{
    /**
     * @var list<string>
     */
    public const FIELDS = [
        'short_description',
        'product_description',
        'story_inspiration',
        'materials_process',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function syncForArtwork(Artwork $artwork, array $data): ArtworkPublishingProfile
    {
        $normalized = [];

        foreach (self::FIELDS as $field) {
            $value = $data[$field] ?? null;
            $normalized[$field] = is_string($value) && trim($value) === '' ? null : $value;
        }

        /** @var ArtworkPublishingProfile $profile */
        $profile = $artwork->publishingProfile()->updateOrCreate(
            ['artwork_id' => $artwork->id],
            $normalized,
        );

        return $profile;
    }
}
