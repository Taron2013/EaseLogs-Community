<?php

namespace App\Support\ArtworkPhotoBulkImport;

use App\Models\Artwork;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class PhotoImportArtworkSearch
{
    private const MAX_TERM_LENGTH = 255;

    private const DEFAULT_LIMIT = 24;

    public function __construct(
        private readonly ManualResolveTitleSuggester $titleSuggester,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function search(User $user, ?string $term, int $limit = self::DEFAULT_LIMIT): array
    {
        $term = $this->normalizeTerm($term);
        $photoRelation = $this->photoRelationName();

        if ($term === null) {
            return $this->formatArtworks(
                $this->baseQuery($user, $photoRelation)->limit($limit)->get(),
                $photoRelation,
            );
        }

        $results = $this->literalSearch($user, $term, $limit, $photoRelation);

        if (count($results) >= $limit) {
            return $results;
        }

        $existingIds = array_map(
            fn (array $card): int => (int) $card['id'],
            $results,
        );

        $artworks = Artwork::query()
            ->where('user_id', $user->id)
            ->with($photoRelation)
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        foreach ($this->titleSuggester->suggest($term, $artworks, $limit) as $suggestion) {
            $artworkId = $suggestion['artwork']->id;

            if (in_array($artworkId, $existingIds, true)) {
                continue;
            }

            $results[] = $this->formatArtworkCard($suggestion['artwork'], $photoRelation);
            $existingIds[] = $artworkId;

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function literalSearch(User $user, string $term, int $limit, string $photoRelation): array
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query = $this->baseQuery($user, $photoRelation)
            ->where(function (Builder $builder) use ($like): void {
                $builder->where('title', 'like', $like)
                    ->orWhere('notes', 'like', $like);

                if ($this->supportsSkuSearch()) {
                    $builder->orWhere('inventory_code', 'like', $like)
                        ->orWhere('sku', 'like', $like);
                }
            })
            ->limit($limit);

        return $this->formatArtworks($query->get(), $photoRelation);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Artwork>
     */
    private function baseQuery(User $user, string $photoRelation): Builder
    {
        return Artwork::query()
            ->where('user_id', $user->id)
            ->with($photoRelation)
            ->orderBy('title')
            ->orderBy('id');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Artwork>  $artworks
     * @return list<array<string, mixed>>
     */
    private function formatArtworks($artworks, string $photoRelation): array
    {
        return $artworks
            ->map(fn (Artwork $artwork): array => $this->formatArtworkCard($artwork, $photoRelation))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatArtworkCard(Artwork $artwork, string $photoRelation): array
    {
        $photo = $artwork->{$photoRelation};

        $card = [
            'id' => $artwork->id,
            'title' => $artwork->displayTitle(),
            'dimensions' => $artwork->formattedDimensions(),
            'completed_date' => $artwork->formattedDisplayCompletedDate(),
            'thumbnail_url' => ($photo && $photo->existsOnDisk()) ? $photo->publicUrl() : null,
        ];

        if ($this->supportsSkuSearch()) {
            $card['sku'] = $artwork->sku;
            $card['inventory_code'] = $artwork->inventory_code;
        }

        return $card;
    }

    private function supportsSkuSearch(): bool
    {
        return Schema::hasColumn('artworks', 'sku')
            && Schema::hasColumn('artworks', 'inventory_code');
    }

    private function photoRelationName(): string
    {
        return method_exists(Artwork::class, 'currentPhoto') && $this->supportsSkuSearch()
            ? 'currentPhoto'
            : 'latestPhoto';
    }

    private function normalizeTerm(?string $term): ?string
    {
        if ($term === null) {
            return null;
        }

        $term = trim($term);

        if ($term === '') {
            return null;
        }

        return mb_substr($term, 0, self::MAX_TERM_LENGTH);
    }
}
