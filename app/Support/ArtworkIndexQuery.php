<?php

namespace App\Support;

use App\Models\Artwork;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ArtworkIndexQuery
{
    public function __construct(
        private readonly ArtworkIndexFilters $filters,
        private readonly ArtworkIndexSearch $search,
        private readonly ArtworkIndexSort $sort,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            ArtworkIndexFilters::fromRequest($request),
            ArtworkIndexSearch::fromRequest($request),
            ArtworkIndexSort::fromRequest($request),
        );
    }

    public function filters(): ArtworkIndexFilters
    {
        return $this->filters;
    }

    public function search(): ArtworkIndexSearch
    {
        return $this->search;
    }

    public function sort(): ArtworkIndexSort
    {
        return $this->sort;
    }

    /**
     * @return Builder<Artwork>
     */
    public function baseQuery(): Builder
    {
        return Artwork::query();
    }

    /**
     * @param  Builder<Artwork>  $query
     */
    public function applyTo(Builder $query, ?int $userId = null): void
    {
        $this->filters->apply($query, $userId);
        $this->search->apply($query, $userId);
        $this->sort->apply($query);
    }

    public function hasActiveModifiers(): bool
    {
        return $this->filters->hasActiveFilters()
            || $this->search->hasTerm()
            || ! $this->sort->usesDefaultListing();
    }

    /**
     * @return array<string, string>
     */
    public function queryParams(): array
    {
        return array_merge(
            $this->filters->queryParams(),
            $this->search->queryParams(),
            $this->sort->queryParams(),
        );
    }
}
