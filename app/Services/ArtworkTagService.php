<?php

namespace App\Services;

use App\Models\Artwork;
use App\Models\ArtworkTag;
use App\Models\User;
use App\Support\ArtworkTagType;
use Illuminate\Support\Collection;

class ArtworkTagService
{
    /**
     * @var list<string>
     */
    public const EXAMPLE_STYLE_TAGS = [
        'Abstract',
        'Cubism',
        'Impressionism',
        'Stained-Glass Cubism',
        'Photorealism',
    ];

    /**
     * @var list<string>
     */
    public const EXAMPLE_SUBJECT_TAGS = [
        'Landscape',
        'Portrait',
        'Animal',
        'Floral',
        'Dragon',
        'Ocean',
        'Mountain',
    ];

    /**
     * @var list<string>
     */
    public const EXAMPLE_GENERAL_TAGS = [
        'TikTok Live',
        'Contest Entry',
        'Needs Better Photo',
        'Experimental',
        'Gift',
        'Sold',
    ];

    /**
     * @deprecated Use EXAMPLE_STYLE_TAGS, EXAMPLE_SUBJECT_TAGS, or EXAMPLE_GENERAL_TAGS.
     *
     * @var list<string>
     */
    public const EXAMPLE_TAGS = [
        ...self::EXAMPLE_STYLE_TAGS,
        ...self::EXAMPLE_SUBJECT_TAGS,
        ...self::EXAMPLE_GENERAL_TAGS,
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

        return $this->dedupeTagNamesPreservingFirst(array_values(array_filter(array_map(
            fn (string $tag): string => trim($tag),
            $tags,
        ), fn (string $tag): bool => $tag !== '')));
    }

    /**
     * @param  list<string>|string|null  $input
     * @return list<string>
     */
    public function parseTagList(mixed $input): array
    {
        if (is_array($input)) {
            return $this->dedupeTagNamesPreservingFirst(array_values(array_filter(array_map(
                fn (mixed $tag): string => trim((string) $tag),
                $input,
            ), fn (string $tag): bool => $tag !== '')));
        }

        return $this->parseTagInput(is_string($input) ? $input : null);
    }

    /**
     * @param  list<string>  ...$lists
     * @return list<string>
     */
    public function mergeParsedTagLists(array ...$lists): array
    {
        $merged = [];

        foreach ($lists as $list) {
            $merged = array_merge($merged, $list);
        }

        return $this->dedupeTagNamesPreservingFirst($merged);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    public function tagsByTypeFromRequestInput(array $input): array
    {
        $hasTypedFields = array_key_exists('style_tags', $input)
            || array_key_exists('subject_tags', $input)
            || array_key_exists('general_tags', $input);

        if ($hasTypedFields) {
            return [
                ArtworkTagType::STYLE => $this->parseTagList($input['style_tags'] ?? []),
                ArtworkTagType::SUBJECT => $this->parseTagList($input['subject_tags'] ?? []),
                ArtworkTagType::GENERAL => $this->mergeParsedTagLists(
                    $this->parseTagList($input['general_tags'] ?? []),
                    $this->parseTagInput(isset($input['tags']) ? (string) $input['tags'] : null),
                ),
            ];
        }

        return [
            ArtworkTagType::STYLE => [],
            ArtworkTagType::SUBJECT => [],
            ArtworkTagType::GENERAL => $this->parseTagInput(isset($input['tags']) ? (string) $input['tags'] : null),
        ];
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function dedupeTagNamesPreservingFirst(array $names): array
    {
        $seen = [];
        $result = [];

        foreach ($names as $name) {
            $trimmed = trim($name);

            if ($trimmed === '') {
                continue;
            }

            $normalized = self::normalizeName($trimmed);

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $result[] = $trimmed;
        }

        return array_values($result);
    }

    public function findOrCreateForUser(User $user, string $name, string $type = ArtworkTagType::GENERAL): ArtworkTag
    {
        $normalized = self::normalizeName($name);
        $type = ArtworkTagType::normalize($type);

        return ArtworkTag::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'type' => $type,
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
    public function resolveTagsForUser(User $user, array $tagNames, string $type = ArtworkTagType::GENERAL): Collection
    {
        return collect($tagNames)
            ->map(fn (string $name): ArtworkTag => $this->findOrCreateForUser($user, $name, $type));
    }

    /**
     * @param  array<string, list<string>>  $tagsByType
     */
    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    public function communityTagsFromRequestInput(array $input): array
    {
        return $this->parseTagList($input['tags'] ?? []);
    }

    /**
     * @param  list<string>  $generalTagNames
     */
    public function syncCommunityTagsForArtwork(Artwork $artwork, User $user, array $generalTagNames): void
    {
        $preservedIds = $artwork->tags()
            ->where('user_id', $user->id)
            ->whereIn('type', [ArtworkTagType::STYLE, ArtworkTagType::SUBJECT])
            ->pluck('artwork_tags.id')
            ->all();

        $generalIds = $this->resolveTagsForUser($user, $generalTagNames, ArtworkTagType::GENERAL)
            ->pluck('id')
            ->all();

        $artwork->tags()->sync(array_values(array_unique(array_merge($preservedIds, $generalIds))));
    }

    /**
     * @return Collection<int, string>
     */
    public function userTagNameOptions(User $user): Collection
    {
        return ArtworkTag::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->pluck('name')
            ->values();
    }

    /**
     * @return Collection<int, ArtworkTag>
     */
    public function userTagsWithUsage(User $user): Collection
    {
        return ArtworkTag::query()
            ->where('user_id', $user->id)
            ->withCount('artworks')
            ->orderBy('name')
            ->get();
    }

    public function syncTypedTagsForArtwork(Artwork $artwork, User $user, array $tagsByType): void
    {
        $tagIds = [];

        foreach (ArtworkTagType::all() as $type) {
            $names = $tagsByType[$type] ?? [];
            $tagIds = array_merge(
                $tagIds,
                $this->resolveTagsForUser($user, $names, $type)->pluck('id')->all(),
            );
        }

        $artwork->tags()->sync($tagIds);
    }

    public function syncForArtwork(Artwork $artwork, User $user, array $tagNames): void
    {
        $this->syncTypedTagsForArtwork($artwork, $user, [
            ArtworkTagType::STYLE => [],
            ArtworkTagType::SUBJECT => [],
            ArtworkTagType::GENERAL => $tagNames,
        ]);
    }

    /**
     * @param  list<string>  $tagNames
     */
    public function attachToArtwork(Artwork $artwork, User $user, array $tagNames, string $type = ArtworkTagType::GENERAL): void
    {
        $tagIds = $this->resolveTagsForUser($user, $tagNames, $type)->pluck('id')->all();
        $artwork->tags()->syncWithoutDetaching($tagIds);
    }

    /**
     * @param  list<string>  $tagNames
     */
    public function detachFromArtwork(Artwork $artwork, User $user, array $tagNames, ?string $type = null): void
    {
        if ($tagNames === []) {
            return;
        }

        $normalized = array_map([self::class, 'normalizeName'], $tagNames);

        $query = ArtworkTag::query()
            ->where('user_id', $user->id)
            ->whereIn('normalized_name', $normalized);

        if ($type !== null) {
            $query->where('type', ArtworkTagType::normalize($type));
        }

        $tagIds = $query->pluck('id')->all();

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

    /**
     * @param  array<string, string|null>  $rowValues
     * @return array<string, list<string>>
     */
    public function parseCsvTagColumns(array $rowValues): array
    {
        $typed = [
            ArtworkTagType::STYLE => $this->parseTagList($rowValues['style_tags'] ?? null),
            ArtworkTagType::SUBJECT => $this->parseTagList($rowValues['subject_tags'] ?? null),
            ArtworkTagType::GENERAL => $this->parseTagList($rowValues['general_tags'] ?? null),
        ];

        if (isset($rowValues['tags']) && $rowValues['tags'] !== null && trim($rowValues['tags']) !== '') {
            $typed[ArtworkTagType::GENERAL] = array_values(array_unique(array_merge(
                $typed[ArtworkTagType::GENERAL],
                $this->parseTagList($rowValues['tags']),
            )));
        }

        return $typed;
    }

    /**
     * @return array<string, list<string>>
     */
    public function tagsByTypeFromArtwork(Artwork $artwork): array
    {
        $grouped = [
            ArtworkTagType::STYLE => [],
            ArtworkTagType::SUBJECT => [],
            ArtworkTagType::GENERAL => [],
        ];

        foreach ($artwork->tags as $tag) {
            $type = ArtworkTagType::normalize($tag->type);
            $grouped[$type][] = $tag->name;
        }

        foreach ($grouped as $type => $names) {
            sort($names);
            $grouped[$type] = $names;
        }

        return $grouped;
    }

    /**
     * @return Collection<string, Collection<int, string>>
     */
    public function userTagOptionsGrouped(User $user): Collection
    {
        return $this->userTagsGroupedByType($user)
            ->map(fn (Collection $tags): Collection => $tags->pluck('name')->values());
    }

    /**
     * @return Collection<string, Collection<int, ArtworkTag>>
     */
    public function userTagsGroupedByType(User $user): Collection
    {
        return ArtworkTag::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->groupBy(fn (ArtworkTag $tag): string => ArtworkTagType::normalize($tag->type))
            ->map(fn (Collection $tags): Collection => $tags->values());
    }

    /**
     * @param  list<int>  $tagIds
     */
    public function attachTagIdsToArtwork(Artwork $artwork, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }

        $artwork->tags()->syncWithoutDetaching(array_values(array_unique(array_map('intval', $tagIds))));
    }

    /**
     * @param  list<int>  $tagIds
     */
    public function detachTagIdsFromArtwork(Artwork $artwork, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }

        $artwork->tags()->detach(array_values(array_unique(array_map('intval', $tagIds))));
    }

    /**
     * @return Collection<string, Collection<int, ArtworkTag>>
     */
    public function userTagsWithUsageGrouped(User $user): Collection
    {
        return ArtworkTag::query()
            ->where('user_id', $user->id)
            ->withCount('artworks')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (ArtworkTag $tag): string => ArtworkTagType::normalize($tag->type))
            ->map(fn (Collection $tags): Collection => $tags->values());
    }

    public function createTagForUser(User $user, string $name, string $type): ArtworkTag
    {
        $name = trim($name);
        $type = \App\Support\EaseLogsEdition::enforcesGeneralOnlyArtworkTags()
            ? ArtworkTagType::GENERAL
            : ArtworkTagType::normalize($type);

        return ArtworkTag::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'normalized_name' => self::normalizeName($name),
            'type' => $type,
        ]);
    }

    public function updateTagNameForUser(ArtworkTag $tag, string $name): ArtworkTag
    {
        return $this->updateTagForUser($tag, $name, ArtworkTagType::normalize($tag->type));
    }

    public function updateTagForUser(ArtworkTag $tag, string $name, string $type): ArtworkTag
    {
        $name = trim($name);
        $type = \App\Support\EaseLogsEdition::enforcesGeneralOnlyArtworkTags()
            ? ArtworkTagType::normalize($tag->type)
            : ArtworkTagType::normalize($type);

        $tag->update([
            'name' => $name,
            'normalized_name' => self::normalizeName($name),
            'type' => $type,
        ]);

        return $tag->fresh() ?? $tag;
    }

    public function deleteTagIfUnused(ArtworkTag $tag): bool
    {
        if ($tag->artworks()->count() > 0) {
            return false;
        }

        $tag->delete();

        return true;
    }

    public function tagBelongsToUser(ArtworkTag $tag, User $user): bool
    {
        return (int) $tag->user_id === (int) $user->id;
    }
}
