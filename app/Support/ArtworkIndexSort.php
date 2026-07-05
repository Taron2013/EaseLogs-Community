<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ArtworkIndexSort
{
    /**
     * Internal key used when the listing uses the default updated_at order.
     */
    public const DEFAULT_SORT = 'default';

    public const DEFAULT_DIRECTION = 'desc';

    /**
     * Query param value for the dimensions column.
     */
    public const SORT_DIMENSIONS = 'dimensions';

    /**
     * @var array<string, string>
     */
    private const SORTABLE_COLUMNS = [
        'title' => 'title',
        'medium' => 'medium',
        self::SORT_DIMENSIONS => self::SORT_DIMENSIONS,
        'start_date' => 'start_date',
        'completed_date' => 'completed_date',
        'updated_at' => 'updated_at',
    ];

    private string $column;

    private string $direction;

    private bool $usingDefaultListing;

    public function __construct(?string $sort, ?string $direction)
    {
        $resolvedColumn = $this->normalizeSortKey($sort);

        if ($resolvedColumn === null) {
            $this->usingDefaultListing = true;
            $this->column = self::DEFAULT_SORT;
            $this->direction = self::DEFAULT_DIRECTION;

            return;
        }

        $this->usingDefaultListing = false;
        $this->column = $resolvedColumn;
        $this->direction = $this->resolveDirection($direction, $this->column);
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            $request->query('sort'),
            $request->query('direction'),
        );
    }

    public function column(): string
    {
        return $this->column;
    }

    public function direction(): string
    {
        return $this->direction;
    }

    public function usesDefaultListing(): bool
    {
        return $this->usingDefaultListing;
    }

    public function isActive(string $column): bool
    {
        if ($this->usingDefaultListing) {
            return false;
        }

        return $this->column === ($this->normalizeSortKey($column) ?? '');
    }

    public function indicator(string $column): string
    {
        if (! $this->isActive($column)) {
            return '';
        }

        return $this->direction === 'asc' ? '↑' : '↓';
    }

    /**
     * Next direction when the given column header is clicked.
     */
    public function nextDirectionFor(string $column): string
    {
        $column = $this->normalizeSortKey($column) ?? self::DEFAULT_SORT;

        if ($this->usingDefaultListing || $this->column !== $column) {
            return 'asc';
        }

        return $this->direction === 'asc' ? 'desc' : 'asc';
    }

    /**
     * @return array<string, string>
     */
    public function queryParams(): array
    {
        if ($this->usingDefaultListing) {
            return [];
        }

        return [
            'sort' => $this->column,
            'direction' => $this->direction,
        ];
    }

    /**
     * @param  array<string, string>  $preserve
     * @return array<string, string>
     */
    public function queryParamsFor(string $column, array $preserve = []): array
    {
        $normalized = $this->normalizeSortKey($column) ?? self::DEFAULT_SORT;

        return array_merge($preserve, [
            'sort' => $normalized,
            'direction' => $this->nextDirectionFor($normalized),
        ]);
    }

    /**
     * @param  Builder<\App\Models\Artwork>  $query
     */
    public function apply(Builder $query): void
    {
        if ($this->usingDefaultListing) {
            $this->applyDefaultListingSort($query);

            return;
        }

        match ($this->column) {
            'title' => $this->applyTitleSort($query),
            self::SORT_DIMENSIONS => $this->applyDimensionsSort($query),
            'completed_date' => $this->applyCompletedDateSort($query),
            default => $query->orderBy($this->column, $this->direction),
        };
    }

    /**
     * Default listing: most recently updated artwork first.
     *
     * SQL: ORDER BY updated_at DESC
     *
     * @param  Builder<\App\Models\Artwork>  $query
     */
    private function applyDefaultListingSort(Builder $query): void
    {
        $query->orderByDesc('updated_at');
    }

    /**
     * Completed date DESC: nulls first, then newest completed.
     * Completed date ASC: oldest completed first, nulls last.
     *
     * DESC SQL: ORDER BY completed_date IS NULL DESC, completed_date DESC
     * ASC SQL:  ORDER BY completed_date IS NULL ASC, completed_date ASC
     *
     * @param  Builder<\App\Models\Artwork>  $query
     */
    private function applyCompletedDateSort(Builder $query): void
    {
        if ($this->direction === 'desc') {
            $query->orderByRaw('completed_date IS NULL DESC')
                ->orderByDesc('completed_date');

            return;
        }

        $query->orderByRaw('completed_date IS NULL ASC')
            ->orderBy('completed_date');
    }

    /**
     * Title sort treats null/blank as untitled without persisting the literal string.
     *
     * DESC SQL: ORDER BY (title IS NULL OR TRIM(title) = '') DESC,
     *           CASE WHEN (title IS NULL OR TRIM(title) = '') THEN '' ELSE title END DESC
     * ASC SQL:  ORDER BY (title IS NULL OR TRIM(title) = '') ASC,
     *           CASE WHEN (title IS NULL OR TRIM(title) = '') THEN '' ELSE title END ASC
     *
     * @param  Builder<\App\Models\Artwork>  $query
     */
    private function applyTitleSort(Builder $query): void
    {
        $untitledExpression = "(title IS NULL OR TRIM(title) = '')";
        $sortableTitleExpression = "CASE WHEN {$untitledExpression} THEN '' ELSE title END";

        if ($this->direction === 'desc') {
            $query->orderByRaw("{$untitledExpression} DESC")
                ->orderByRaw("{$sortableTitleExpression} DESC");

            return;
        }

        $query->orderByRaw("{$untitledExpression} ASC")
            ->orderByRaw("{$sortableTitleExpression} ASC");
    }

    /**
     * Dimensions sort uses width * height when both exist; depth is ignored.
     *
     * DESC SQL: ORDER BY (width IS NOT NULL AND height IS NOT NULL) ASC,
     *           (width * height) DESC
     * ASC SQL:  ORDER BY (width IS NOT NULL AND height IS NOT NULL) DESC,
     *           (width * height) ASC
     *
     * @param  Builder<\App\Models\Artwork>  $query
     */
    private function applyDimensionsSort(Builder $query): void
    {
        $dimensionedExpression = '(width IS NOT NULL AND height IS NOT NULL)';
        $areaExpression = '(width * height)';

        if ($this->direction === 'desc') {
            $query->orderByRaw("{$dimensionedExpression} ASC")
                ->orderByRaw("{$areaExpression} DESC");

            return;
        }

        $query->orderByRaw("{$dimensionedExpression} DESC")
            ->orderByRaw("{$areaExpression} ASC");
    }

    private function normalizeSortKey(?string $sort): ?string
    {
        $sort = is_string($sort) ? strtolower(trim($sort)) : '';

        if ($sort === '' || ! isset(self::SORTABLE_COLUMNS[$sort])) {
            return null;
        }

        return self::SORTABLE_COLUMNS[$sort];
    }

    private function resolveDirection(?string $direction, string $column): string
    {
        $direction = is_string($direction) ? strtolower(trim($direction)) : '';

        if (in_array($direction, ['asc', 'desc'], true)) {
            return $direction;
        }

        return 'asc';
    }
}
