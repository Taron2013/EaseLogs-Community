<?php

namespace App\Services;

use App\Models\Artwork;
use App\Models\User;

class ArtworkMediumSuggestionService
{
    /**
     * @var list<string>
     */
    public const BUILTIN_DEFAULTS = [
        'Acrylic',
        'Oil',
        'Watercolor',
        'Gouache',
        'Ink',
        'Graphite',
        'Charcoal',
        'Pastel',
        'Colored Pencil',
        'Mixed Media',
        'Digital',
        'Alcohol Ink',
        'Marker',
        'Tempera',
    ];

    public function __construct(
        private readonly ?string $configPath = null,
    ) {}

    /**
     * @return list<string>
     */
    public function configDefaults(): array
    {
        $path = $this->configPath ?? config_path('easelogs_medium_defaults.json');

        if (! is_readable($path)) {
            return self::BUILTIN_DEFAULTS;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return self::BUILTIN_DEFAULTS;
        }

        $mediums = array_values(array_filter(array_map(
            fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $decoded,
        ), fn (string $value): bool => $value !== ''));

        return $mediums !== [] ? $mediums : self::BUILTIN_DEFAULTS;
    }

    /**
     * @return list<string>
     */
    public function formSuggestions(?User $user = null): array
    {
        return $this->configDefaults();
    }

    /**
     * @return list<string>
     */
    public function filterOptions(?User $user = null): array
    {
        return $this->uniqueSorted(array_merge(
            $this->configDefaults(),
            $this->existingArtworkMediums(),
        ));
    }

    /**
     * @return list<string>
     */
    private function existingArtworkMediums(): array
    {
        return Artwork::query()
            ->whereNotNull('medium')
            ->where('medium', '!=', '')
            ->distinct()
            ->orderBy('medium')
            ->pluck('medium')
            ->all();
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueSorted(array $values): array
    {
        $values = array_values(array_unique(array_filter(array_map(
            fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $values,
        ), fn (string $value): bool => $value !== '')));

        sort($values, SORT_NATURAL | SORT_FLAG_CASE);

        return $values;
    }
}
