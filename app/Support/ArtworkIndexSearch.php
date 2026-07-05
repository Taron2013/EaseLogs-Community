<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ArtworkIndexSearch
{
    private const MAX_TERM_LENGTH = 255;

    private ?string $term;

    public function __construct(?string $term)
    {
        $this->term = $this->normalizeTerm($term);
    }

    public static function fromRequest(Request $request): self
    {
        return new self($request->query('q'));
    }

    public function term(): ?string
    {
        return $this->term;
    }

    public function hasTerm(): bool
    {
        return $this->term !== null;
    }

    /**
     * @return array<string, string>
     */
    public function queryParams(): array
    {
        if ($this->term === null) {
            return [];
        }

        return ['q' => $this->term];
    }

    /**
     * @param  Builder<\App\Models\Artwork>  $query
     */
    public function apply(Builder $query, ?int $userId = null): void
    {
        if ($this->term === null) {
            return;
        }

        $like = '%'.addcslashes($this->term, '%_\\').'%';

        $query->where(function (Builder $builder) use ($like, $userId): void {
            $builder->where('title', 'like', $like)
                ->orWhere('notes', 'like', $like);

            if ($userId !== null) {
                $builder->orWhereHas('tags', function (Builder $tagQuery) use ($userId, $like): void {
                    $tagQuery->where('user_id', $userId)
                        ->where('name', 'like', $like);
                });
            }

            $builder->orWhereHas('publishingProfile', function (Builder $profileQuery) use ($like): void {
                $profileQuery->where('short_description', 'like', $like)
                    ->orWhere('product_description', 'like', $like)
                    ->orWhere('story_inspiration', 'like', $like)
                    ->orWhere('materials_process', 'like', $like);
            });
        });
    }

    private function normalizeTerm(mixed $term): ?string
    {
        if (! is_string($term)) {
            return null;
        }

        $term = trim($term);

        if ($term === '') {
            return null;
        }

        return mb_substr($term, 0, self::MAX_TERM_LENGTH);
    }
}
