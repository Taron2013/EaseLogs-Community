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

    public const QUICK_HAS_DIMENSIONS = 'has_dimensions';

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
        self::QUICK_HAS_DIMENSIONS => self::QUICK_HAS_DIMENSIONS,
    ];

    private string $quickFilter;

    private ?string $medium;

    private ?string $tag;

    private ?string $dimensionUnit;

    private ?string $widthMin;

    private ?string $widthMax;

    private ?string $heightMin;

    private ?string $heightMax;

    public function __construct(
        ?string $quickFilter,
        ?string $medium,
        ?string $tag,
        ?string $dimensionUnit,
        ?string $widthMin,
        ?string $widthMax,
        ?string $heightMin,
        ?string $heightMax,
    ) {
        $this->quickFilter = $this->normalizeQuickFilter($quickFilter);
        $this->medium = $this->normalizeFieldValue($medium);
        $this->tag = $this->normalizeFieldValue($tag);
        $this->dimensionUnit = $this->normalizeFieldValue($dimensionUnit);
        $this->widthMin = $this->normalizeNumericValue($widthMin);
        $this->widthMax = $this->normalizeNumericValue($widthMax);
        $this->heightMin = $this->normalizeNumericValue($heightMin);
        $this->heightMax = $this->normalizeNumericValue($heightMax);
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            $request->query('filter'),
            $request->query('medium'),
            $request->query('tag'),
            $request->query('dimension_unit'),
            $request->query('width_min'),
            $request->query('width_max'),
            $request->query('height_min'),
            $request->query('height_max'),
        );
    }

    public function quickFilter(): string
    {
        return $this->quickFilter;
    }

    public function medium(): ?string
    {
        return $this->medium;
    }

    public function tag(): ?string
    {
        return $this->tag;
    }

    public function dimensionUnit(): ?string
    {
        return $this->dimensionUnit;
    }

    public function widthMin(): ?string
    {
        return $this->widthMin;
    }

    public function widthMax(): ?string
    {
        return $this->widthMax;
    }

    public function heightMin(): ?string
    {
        return $this->heightMin;
    }

    public function heightMax(): ?string
    {
        return $this->heightMax;
    }

    public function isQuickFilterActive(string $quickFilter): bool
    {
        return $this->quickFilter === $this->normalizeQuickFilter($quickFilter);
    }

    public function hasActiveFilters(): bool
    {
        return $this->quickFilter !== self::QUICK_ALL
            || $this->medium !== null
            || $this->tag !== null
            || $this->dimensionUnit !== null
            || $this->widthMin !== null
            || $this->widthMax !== null
            || $this->heightMin !== null
            || $this->heightMax !== null;
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

        foreach ([
            'medium' => $this->medium,
            'tag' => $this->tag,
            'dimension_unit' => $this->dimensionUnit,
            'width_min' => $this->widthMin,
            'width_max' => $this->widthMax,
            'height_min' => $this->heightMin,
            'height_max' => $this->heightMax,
        ] as $key => $value) {
            if ($value !== null) {
                $params[$key] = $value;
            }
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
    public function apply(Builder $query, ?int $userId = null): void
    {
        $this->applyQuickFilter($query);
        $this->applyFieldFilters($query, $userId);
        $this->applyDimensionFilters($query);
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
            self::QUICK_HAS_DIMENSIONS => $query->whereNotNull('width')
                ->whereNotNull('height'),
            default => null,
        };
    }

    /**
     * @param  Builder<\App\Models\Artwork>  $query
     */
    private function applyFieldFilters(Builder $query, ?int $userId): void
    {
        if ($this->medium !== null) {
            $query->where('medium', $this->medium);
        }

        if ($this->tag !== null && $userId !== null) {
            $normalizedTag = mb_strtolower($this->tag);

            $query->whereHas('tags', function (Builder $builder) use ($userId, $normalizedTag): void {
                $builder->where('user_id', $userId)
                    ->where('normalized_name', $normalizedTag);
            });
        }
    }

    /**
     * @param  Builder<\App\Models\Artwork>  $query
     */
    private function applyDimensionFilters(Builder $query): void
    {
        if ($this->dimensionUnit !== null) {
            $query->where('dimension_unit', $this->dimensionUnit);
        }

        if ($this->widthMin !== null) {
            $query->where('width', '>=', $this->widthMin);
        }

        if ($this->widthMax !== null) {
            $query->where('width', '<=', $this->widthMax);
        }

        if ($this->heightMin !== null) {
            $query->where('height', '>=', $this->heightMin);
        }

        if ($this->heightMax !== null) {
            $query->where('height', '<=', $this->heightMax);
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

    private function normalizeNumericValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || ! is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return $value;
    }
}
