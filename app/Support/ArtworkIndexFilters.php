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

    public const MEDIUM_PRESENCE_MISSING = 'missing';

    public const MEDIUM_PRESENCE_HAS = 'has';

    public const TAG_PRESENCE_MISSING_ALL = 'missing_all';

    public const TAG_PRESENCE_HAS_ANY = 'has_any';

    public const TAG_PRESENCE_MISSING_STYLE = 'missing_style';

    public const TAG_PRESENCE_HAS_STYLE = 'has_style';

    public const TAG_PRESENCE_MISSING_SUBJECT = 'missing_subject';

    public const TAG_PRESENCE_HAS_SUBJECT = 'has_subject';

    public const TAG_PRESENCE_MISSING_GENERAL = 'missing_general';

    public const TAG_PRESENCE_HAS_GENERAL = 'has_general';

    private const UNTITLED_EXPRESSION = "(title IS NULL OR TRIM(title) = '')";

    private const MISSING_MEDIUM_EXPRESSION = "(medium IS NULL OR TRIM(medium) = '')";

    /**
     * @var array<string, string>
     */
    private const MEDIUM_PRESENCE_FILTERS = [
        self::MEDIUM_PRESENCE_MISSING => self::MEDIUM_PRESENCE_MISSING,
        self::MEDIUM_PRESENCE_HAS => self::MEDIUM_PRESENCE_HAS,
    ];

    /**
     * @var array<string, string>
     */
    private const TAG_PRESENCE_FILTERS = [
        self::TAG_PRESENCE_MISSING_ALL => self::TAG_PRESENCE_MISSING_ALL,
        self::TAG_PRESENCE_HAS_ANY => self::TAG_PRESENCE_HAS_ANY,
        self::TAG_PRESENCE_MISSING_STYLE => self::TAG_PRESENCE_MISSING_STYLE,
        self::TAG_PRESENCE_HAS_STYLE => self::TAG_PRESENCE_HAS_STYLE,
        self::TAG_PRESENCE_MISSING_SUBJECT => self::TAG_PRESENCE_MISSING_SUBJECT,
        self::TAG_PRESENCE_HAS_SUBJECT => self::TAG_PRESENCE_HAS_SUBJECT,
        self::TAG_PRESENCE_MISSING_GENERAL => self::TAG_PRESENCE_MISSING_GENERAL,
        self::TAG_PRESENCE_HAS_GENERAL => self::TAG_PRESENCE_HAS_GENERAL,
    ];

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

    private ?string $mediumPresence;

    private ?string $tagPresence;

    /** @var list<string> */
    private array $styleTags;

    /** @var list<string> */
    private array $subjectTags;

    /** @var list<string> */
    private array $generalTags;

    /** @deprecated Legacy single-tag filter; matches any tag type. */
    private ?string $legacyTag;

    private ?string $dimensionUnit;

    private ?string $widthMin;

    private ?string $widthMax;

    private ?string $heightMin;

    private ?string $heightMax;

    /**
     * @param  list<string>  $styleTags
     * @param  list<string>  $subjectTags
     * @param  list<string>  $generalTags
     */
    public function __construct(
        ?string $quickFilter,
        ?string $medium,
        ?string $mediumPresence,
        ?string $tagPresence,
        array $styleTags,
        array $subjectTags,
        array $generalTags,
        ?string $legacyTag,
        ?string $dimensionUnit,
        ?string $widthMin,
        ?string $widthMax,
        ?string $heightMin,
        ?string $heightMax,
    ) {
        $this->quickFilter = $this->normalizeQuickFilter($quickFilter);
        $this->medium = $this->normalizeFieldValue($medium);
        $this->mediumPresence = $this->normalizeMediumPresence($mediumPresence);
        $this->tagPresence = $this->normalizeTagPresence($tagPresence);
        $this->styleTags = $this->normalizeTagList($styleTags);
        $this->subjectTags = $this->normalizeTagList($subjectTags);
        $this->generalTags = $this->normalizeTagList($generalTags);
        $this->legacyTag = $this->normalizeFieldValue($legacyTag);
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
            $request->query('medium_presence'),
            $request->query('tag_presence'),
            self::tagListFromRequest($request, 'style_tags'),
            self::tagListFromRequest($request, 'subject_tags'),
            self::tagListFromRequest($request, 'general_tags'),
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

    public function mediumPresence(): ?string
    {
        return $this->mediumPresence;
    }

    public function tagPresence(): ?string
    {
        return $this->tagPresence;
    }

    /**
     * @return list<string>
     */
    public function styleTags(): array
    {
        return $this->styleTags;
    }

    /**
     * @return list<string>
     */
    public function subjectTags(): array
    {
        return $this->subjectTags;
    }

    /**
     * @return list<string>
     */
    public function generalTags(): array
    {
        return $this->generalTags;
    }

    /**
     * @deprecated Use typed tag filters instead.
     */
    public function tag(): ?string
    {
        return $this->legacyTag;
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
            || $this->mediumPresence !== null
            || $this->tagPresence !== null
            || $this->styleTags !== []
            || $this->subjectTags !== []
            || $this->generalTags !== []
            || $this->legacyTag !== null
            || $this->dimensionUnit !== null
            || $this->widthMin !== null
            || $this->widthMax !== null
            || $this->heightMin !== null
            || $this->heightMax !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function queryParams(): array
    {
        $params = [];

        if ($this->quickFilter !== self::QUICK_ALL) {
            $params['filter'] = $this->quickFilter;
        }

        foreach ([
            'medium' => $this->medium,
            'medium_presence' => $this->mediumPresence,
            'tag_presence' => $this->tagPresence,
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

        foreach ([
            'style_tags' => $this->styleTags,
            'subject_tags' => $this->subjectTags,
            'general_tags' => $this->generalTags,
        ] as $key => $values) {
            if ($values !== []) {
                $params[$key] = $values;
            }
        }

        if ($this->legacyTag !== null) {
            $params['tag'] = $this->legacyTag;
        }

        return $params;
    }

    /**
     * @return array<string, mixed>
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
        $this->applyMediumPresenceFilter($query);
        $this->applyFieldFilters($query, $userId);
        $this->applyTagPresenceFilter($query, $userId);
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
    private function applyMediumPresenceFilter(Builder $query): void
    {
        match ($this->mediumPresence) {
            self::MEDIUM_PRESENCE_MISSING => $query->whereRaw(self::MISSING_MEDIUM_EXPRESSION),
            self::MEDIUM_PRESENCE_HAS => $query->whereNotNull('medium')
                ->whereRaw("TRIM(medium) != ''"),
            default => null,
        };
    }

    /**
     * @param  Builder<\App\Models\Artwork>  $query
     */
    private function applyTagPresenceFilter(Builder $query, ?int $userId): void
    {
        match ($this->tagPresence) {
            self::TAG_PRESENCE_MISSING_ALL => $query->whereDoesntHave('tags'),
            self::TAG_PRESENCE_HAS_ANY => $query->whereHas('tags'),
            self::TAG_PRESENCE_MISSING_STYLE => $this->applyMissingTypedTagPresence($query, $userId, ArtworkTagType::STYLE),
            self::TAG_PRESENCE_HAS_STYLE => $this->applyHasTypedTagPresence($query, $userId, ArtworkTagType::STYLE),
            self::TAG_PRESENCE_MISSING_SUBJECT => $this->applyMissingTypedTagPresence($query, $userId, ArtworkTagType::SUBJECT),
            self::TAG_PRESENCE_HAS_SUBJECT => $this->applyHasTypedTagPresence($query, $userId, ArtworkTagType::SUBJECT),
            self::TAG_PRESENCE_MISSING_GENERAL => $this->applyMissingTypedTagPresence($query, $userId, ArtworkTagType::GENERAL),
            self::TAG_PRESENCE_HAS_GENERAL => $this->applyHasTypedTagPresence($query, $userId, ArtworkTagType::GENERAL),
            default => null,
        };
    }

    /**
     * @param  Builder<\App\Models\Artwork>  $query
     */
    private function applyMissingTypedTagPresence(Builder $query, ?int $userId, string $type): void
    {
        $query->whereDoesntHave('tags', function (Builder $builder) use ($userId, $type): void {
            if ($userId !== null) {
                $builder->where('user_id', $userId);
            }

            $builder->where('type', ArtworkTagType::normalize($type));
        });
    }

    /**
     * @param  Builder<\App\Models\Artwork>  $query
     */
    private function applyHasTypedTagPresence(Builder $query, ?int $userId, string $type): void
    {
        $query->whereHas('tags', function (Builder $builder) use ($userId, $type): void {
            if ($userId !== null) {
                $builder->where('user_id', $userId);
            }

            $builder->where('type', ArtworkTagType::normalize($type));
        });
    }

    /**
     * @param  Builder<\App\Models\Artwork>  $query
     */
    private function applyFieldFilters(Builder $query, ?int $userId): void
    {
        if ($this->medium !== null) {
            $query->where('medium', $this->medium);
        }

        if ($userId === null) {
            return;
        }

        $this->applyTypedTagFilter($query, $userId, ArtworkTagType::STYLE, $this->styleTags);
        $this->applyTypedTagFilter($query, $userId, ArtworkTagType::SUBJECT, $this->subjectTags);
        $this->applyTypedTagFilter($query, $userId, ArtworkTagType::GENERAL, $this->generalTags);

        if ($this->legacyTag !== null) {
            $normalizedTag = mb_strtolower($this->legacyTag);

            $query->whereHas('tags', function (Builder $builder) use ($userId, $normalizedTag): void {
                $builder->where('user_id', $userId)
                    ->where('normalized_name', $normalizedTag);
            });
        }
    }

    /**
     * @param  Builder<\App\Models\Artwork>  $query
     * @param  list<string>  $tagNames
     */
    private function applyTypedTagFilter(Builder $query, int $userId, string $type, array $tagNames): void
    {
        if ($tagNames === []) {
            return;
        }

        $normalized = array_map(
            fn (string $name): string => mb_strtolower($name),
            $tagNames,
        );

        $query->whereHas('tags', function (Builder $builder) use ($userId, $type, $normalized): void {
            $builder->where('user_id', $userId)
                ->where('type', ArtworkTagType::normalize($type))
                ->whereIn('normalized_name', $normalized);
        });
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

    private function normalizeMediumPresence(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        return self::MEDIUM_PRESENCE_FILTERS[$value] ?? null;
    }

    private function normalizeTagPresence(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        return self::TAG_PRESENCE_FILTERS[$value] ?? null;
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

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function normalizeTagList(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value === '') {
                continue;
            }

            $normalized[] = mb_substr($value, 0, self::MAX_FIELD_VALUE_LENGTH);
        }

        return array_values(array_unique($normalized));
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

    /**
     * @return list<string>
     */
    private static function tagListFromRequest(Request $request, string $key): array
    {
        $value = $request->query($key);

        if (is_array($value)) {
            return array_values(array_filter(array_map(
                fn (mixed $tag): string => trim((string) $tag),
                $value,
            ), fn (string $tag): bool => $tag !== ''));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            trim(...),
            preg_split('/\s*,\s*/', trim($value)) ?: [],
        ), fn (string $tag): bool => $tag !== ''));
    }
}
