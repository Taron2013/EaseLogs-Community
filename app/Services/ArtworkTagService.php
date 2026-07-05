<?php

namespace App\Services;

use App\Models\Artwork;
use App\Models\ArtworkTag;
use App\Models\User;
use Illuminate\Support\Collection;

class ArtworkTagService
{
    /**
     * @var list<string>
     */
    public const EXAMPLE_TAGS = [
        'Cubism',
        'Abstract',
        'Landscape',
        'Animal',
        'Floral',
        'Portrait',
        'Pour Painting',
        'Mixed Media',
    ];

    public static function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * @return list<string>
     */
    public function parseTagInput(?string $input): array
    {
        if ($input === null || trim($input) === '') {
            return [];
        }

        $tags = preg_split('/\s*,\s*/', trim($input)) ?: [];

        return array_values(array_filter(array_map(
            fn (string $tag): string => trim($tag),
            $tags,
        ), fn (string $tag): bool => $tag !== ''));
    }

    public function findOrCreateForUser(User $user, string $name): ArtworkTag
    {
        $normalized = self::normalizeName($name);

        return ArtworkTag::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'normalized_name' => $normalized,
            ],
            [
                'name' => trim($name),
            ],
        );
    }

    /**
     * @param  list<string>  $tagNames
     * @return Collection<int, ArtworkTag>
     */
    public function resolveTagsForUser(User $user, array $tagNames): Collection
    {
        return collect($tagNames)
            ->map(fn (string $name): ArtworkTag => $this->findOrCreateForUser($user, $name));
    }

    public function syncForArtwork(Artwork $artwork, User $user, array $tagNames): void
    {
        $tags = $this->resolveTagsForUser($user, $tagNames);
        $artwork->tags()->sync($tags->pluck('id')->all());
    }

    /**
     * @param  list<string>  $tagNames
     */
    public function attachToArtwork(Artwork $artwork, User $user, array $tagNames): void
    {
        $tagIds = $this->resolveTagsForUser($user, $tagNames)->pluck('id')->all();
        $artwork->tags()->syncWithoutDetaching($tagIds);
    }

    /**
     * @param  list<string>  $tagNames
     */
    public function detachFromArtwork(Artwork $artwork, User $user, array $tagNames): void
    {
        if ($tagNames === []) {
            return;
        }

        $normalized = array_map([self::class, 'normalizeName'], $tagNames);

        $tagIds = ArtworkTag::query()
            ->where('user_id', $user->id)
            ->whereIn('normalized_name', $normalized)
            ->pluck('id')
            ->all();

        if ($tagIds !== []) {
            $artwork->tags()->detach($tagIds);
        }
    }

    /**
     * @param  list<string>  $tagNames
     */
    public function replaceOnArtwork(Artwork $artwork, User $user, array $tagNames): void
    {
        $this->syncForArtwork($artwork, $user, $tagNames);
    }
}
