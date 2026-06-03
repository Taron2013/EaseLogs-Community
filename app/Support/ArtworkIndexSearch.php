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
    public function apply(Builder $query): void
    {
        if ($this->term === null) {
            return;
        }

        $like = '%'.addcslashes($this->term, '%_\\').'%';

        $query->where(function (Builder $builder) use ($like): void {
            $builder->where('title', 'like', $like)
                ->orWhere('notes', 'like', $like);
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
