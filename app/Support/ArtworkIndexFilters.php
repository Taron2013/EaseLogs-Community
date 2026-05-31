<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ArtworkIndexFilters
{
    public const QUICK_ALL = 'all';

    public const QUICK_IN_PROGRESS = 'in_progress';

    public const QUICK_COMPLETED = 'completed';

    public const QUICK_UNTITLED = 'untitled';

    public const QUICK_MISSING_PHOTO = 'missing_photo';

    public const QUICK_MISSING_DIMENSIONS = 'missing_dimensions';

    private const UNTITLED_EXPRESSION = "(title IS NULL OR TRIM(title) = '')";

    private const MAX_FIELD_VALUE_LENGTH = 255;

    /**
     * @var array<string, string>
     */
    private const QUICK_FILTERS = [
        self::QUICK_IN_PROGRESS => self::QUICK_IN_PROGRESS,
        self::QUICK_COMPLETED => self::QUICK_COMPLETED,
        self::QUICK_UNTITLED => self::QUICK_UNTITLED,
        self::QUICK_MISSING_PHOTO => self::QUICK_MISSING_PHOTO,
        self::QUICK_MISSING_DIMENSIONS => self::QUICK_MISSING_DIMENSIONS,
    ];

    private string $quickFilter;

    private ?string $artworkType;

    private ?string $medium;

    public function __construct(?string $quickFilter, ?string $artworkType, ?string $medium)
    {
        $this->quickFilter = $this->normalizeQuickFilter($quickFilter);
        $this->artworkType = $this->normalizeFieldValue($artworkType);
        $this->medium = $this->normalizeFieldValue($medium);
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            $request->query('filter'),
            $request->query('artwork_type'),
            $request->query('medium'),
        );
    }

    public function quickFilter(): string
    {
        return $this->quickFilter;
    }

    public function artworkType(): ?string
    {
        return $this->artworkType;
    }

    public function medium(): ?string
    {
        return $this->medium;
    }

    public function isQuickFilterActive(string $quickFilter): bool
    {
        return $this->quickFilter === $this->normalizeQuickFilter($quickFilter);
    }

    public function hasActiveFilters(): bool
    {
        return $this->quickFilter !== self::QUICK_ALL
            || $this->artworkType !== null
            || $this->medium !== null;
    }

    /**
     * @return array<string, string>
     */
    public function queryParams(): array
    {
        $params = [];

        if ($this->quickFilter !== self::QUICK_ALL) {
            $params['filter'] = $this->quickFilter;
        }

        if ($this->artworkType !== null) {
            $params['artwork_type'] = $this->artworkType;
        }

        if ($this->medium !== null) {
            $params['medium'] = $this->medium;
        }

        return $params;
    }

    /**
     * @return array<string, string>
     */
    public function queryParamsForQuickFilter(string $quickFilter): array
    {
        $params = $this->queryParams();

        if ($this->normalizeQuickFilter($quickFilter) === self::QUICK_ALL) {
            unset($params['filter']);
        } else {
            $params['filter'] = $this->normalizeQuickFilter($quickFilter);
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Artwork>  $query
     */
    public function apply(Builder $query): void
    {
        $this->applyQuickFilter($query);
        $this->applyFieldFilters($query);
    }

    /**
     * @param  Builder<\App\Models\Artwork>  $query
     */
    private function applyQuickFilter(Builder $query): void
    {
        match ($this->quickFilter) {
            self::QUICK_IN_PROGRESS => $query->whereNull('completed_date'),
            self::QUICK_COMPLETED => $query->whereNotNull('completed_date'),
            self::QUICK_UNTITLED => $query->whereRaw(self::UNTITLED_EXPRESSION),
            self::QUICK_MISSING_PHOTO => $query->whereDoesntHave('photos'),
            self::QUICK_MISSING_DIMENSIONS => $query->where(function (Builder $builder): void {
                $builder->whereNull('width')
                    ->orWhereNull('height');
            }),
            default => null,
        };
    }

    /**
     * @param  Builder<\App\Models\Artwork>  $query
     */
    private function applyFieldFilters(Builder $query): void
    {
        if ($this->artworkType !== null) {
            $query->where('artwork_type', $this->artworkType);
        }

        if ($this->medium !== null) {
            $query->where('medium', $this->medium);
        }
    }

    private function normalizeQuickFilter(?string $filter): string
    {
        $filter = is_string($filter) ? strtolower(trim($filter)) : '';

        if ($filter === '' || $filter === self::QUICK_ALL) {
            return self::QUICK_ALL;
        }

        return self::QUICK_FILTERS[$filter] ?? self::QUICK_ALL;
    }

    private function normalizeFieldValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, self::MAX_FIELD_VALUE_LENGTH);
    }
}
