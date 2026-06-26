<?php

namespace App\Support\ArtworkPhotoBulkImport;

use App\Models\Artwork;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ArtworkPhotoMatcher
{
    public function __construct(
        private readonly PartialTitleMatcher $partialTitleMatcher = new PartialTitleMatcher,
    ) {}

    /**
     * @return array{artwork: ?Artwork, method: ?string, error: ?string}
     */
    public function matchByArtworkId(User $user, ?int $artworkId): array
    {
        if ($artworkId === null) {
            return [
                'artwork' => null,
                'method' => null,
                'error' => 'Each row must include artwork_id or a title/name candidate.',
            ];
        }

        $artwork = Artwork::query()
            ->where('user_id', $user->id)
            ->whereKey($artworkId)
            ->first();

        if ($artwork === null) {
            return [
                'artwork' => null,
                'method' => 'artwork_id',
                'error' => "No artwork found with id {$artworkId} for this account.",
            ];
        }

        return ['artwork' => $artwork, 'method' => 'artwork_id', 'error' => null];
    }

    /**
     * @return array{
     *     artwork: ?Artwork,
     *     artworks: Collection<int, Artwork>,
     *     method: string,
     *     error: ?string,
     *     ambiguous: bool
     * }
     */
    public function matchByTitleCandidate(User $user, ?string $candidate): array
    {
        $candidate = $this->normalizeTitle($candidate);

        if ($candidate === null || $this->isUntitled($candidate)) {
            return [
                'artwork' => null,
                'artworks' => new Collection,
                'method' => 'title_candidate',
                'error' => 'Title matching is not available for Untitled artworks.',
                'ambiguous' => false,
                'exact' => false,
            ];
        }

        $artworks = Artwork::query()
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(TRIM(title)) = ?', [strtolower($candidate)])
            ->orderBy('id')
            ->get();

        if ($artworks->count() === 1) {
            return [
                'artwork' => $artworks->first(),
                'artworks' => $artworks,
                'method' => 'title_candidate',
                'error' => null,
                'ambiguous' => false,
                'exact' => true,
            ];
        }

        if ($artworks->count() > 1) {
            return [
                'artwork' => null,
                'artworks' => $artworks,
                'method' => 'title_candidate',
                'error' => "Multiple artworks match title \"{$candidate}\".",
                'ambiguous' => true,
                'exact' => true,
            ];
        }

        return [
            'artwork' => null,
            'artworks' => new Collection,
            'method' => 'title_candidate',
            'error' => "No artwork found with title \"{$candidate}\".",
            'ambiguous' => false,
            'exact' => false,
        ];
    }

    /**
     * @return array{
     *     artwork: ?Artwork,
     *     artworks: Collection<int, Artwork>,
     *     method: string,
     *     error: ?string,
     *     ambiguous: bool,
     *     confidence: ?float
     * }
     */
    public function matchByPartialTitleCandidate(User $user, ?string $candidate): array
    {
        $candidate = $this->normalizeTitle($candidate);

        if ($candidate === null || $this->isUntitled($candidate)) {
            return [
                'artwork' => null,
                'artworks' => new Collection,
                'method' => 'partial_title_match',
                'error' => 'Title matching is not available for Untitled artworks.',
                'ambiguous' => false,
                'confidence' => null,
            ];
        }

        $artworks = Artwork::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get();

        $result = $this->partialTitleMatcher->findBestMatch($candidate, $artworks);

        return [
            'artwork' => $result['artwork'],
            'artworks' => $result['artworks'],
            'method' => 'partial_title_match',
            'error' => $result['error'],
            'ambiguous' => $result['ambiguous'],
            'confidence' => $result['confidence'],
        ];
    }

    private function normalizeTitle(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return $value === '' ? null : $value;
    }

    private function isUntitled(string $title): bool
    {
        return strcasecmp(trim($title), 'untitled') === 0;
    }
}
