<?php

namespace App\Support\ArtworkPhotoBulkImport;

use App\Models\Artwork;
use Illuminate\Database\Eloquent\Collection;

final class ManualResolveTitleSuggester
{
    private const MIN_SUGGESTION_TOKENS = 2;

    /**
     * @var list<string>
     */
    private const DESCRIPTOR_TOKENS = [
        'aqua', 'amber', 'azure', 'beige', 'black', 'blue', 'bronze', 'brown', 'burgundy',
        'charcoal', 'copper', 'coral', 'cream', 'crimson', 'cyan', 'emerald', 'gold', 'golden',
        'gray', 'green', 'grey', 'indigo', 'ivory', 'lavender', 'lime', 'magenta', 'maroon',
        'mint', 'navy', 'olive', 'orange', 'peach', 'pink', 'purple', 'red', 'ruby', 'rust',
        'salmon', 'sapphire', 'scarlet', 'silver', 'slate', 'tan', 'teal', 'topaz', 'turquoise',
        'violet', 'white', 'yellow',
    ];

    public function __construct(
        private readonly PartialTitleMatcher $matcher,
    ) {}

    /**
     * @param  Collection<int, Artwork>  $artworks
     * @return list<array{artwork: Artwork, score: float}>
     */
    public function suggest(string $candidate, Collection $artworks, int $limit = 24): array
    {
        $variants = $this->phraseVariants($candidate);

        if ($variants === []) {
            return [];
        }

        $scored = [];

        foreach ($artworks as $artwork) {
            if ($this->isUntitled($artwork->title)) {
                continue;
            }

            $bestScore = 0.0;

            foreach ($variants as $variant) {
                $bestScore = max($bestScore, $this->matcher->score($variant, $artwork->title));
            }

            if ($bestScore >= PartialTitleMatcher::MIN_CONFIDENCE) {
                $scored[] = [
                    'artwork' => $artwork,
                    'score' => $bestScore,
                ];
            }
        }

        usort($scored, function (array $left, array $right): int {
            $scoreCompare = $right['score'] <=> $left['score'];

            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return $left['artwork']->id <=> $right['artwork']->id;
        });

        return array_slice($scored, 0, $limit);
    }

    /**
     * @return list<string>
     */
    private function phraseVariants(string $candidate): array
    {
        $candidate = trim($candidate);

        if ($candidate === '' || $this->isUntitled($candidate)) {
            return [];
        }

        $variants = [$candidate];

        $tokens = $this->matcher->meaningfulTokens($candidate);

        if ($tokens === []) {
            return array_values(array_unique($variants));
        }

        $variants[] = implode(' ', $tokens);

        $withoutDescriptors = $this->stripTrailingDescriptorTokens($tokens);

        if ($withoutDescriptors !== [] && $withoutDescriptors !== $tokens) {
            $variants[] = implode(' ', $withoutDescriptors);
        }

        $prefixTokens = $withoutDescriptors !== [] ? $withoutDescriptors : $tokens;

        while (count($prefixTokens) > self::MIN_SUGGESTION_TOKENS) {
            array_pop($prefixTokens);
            $variants[] = implode(' ', $prefixTokens);
        }

        return array_values(array_unique(array_filter(
            $variants,
            fn (string $variant): bool => $variant !== '' && ! $this->isUntitled($variant),
        )));
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function stripTrailingDescriptorTokens(array $tokens): array
    {
        $tokens = array_values($tokens);

        while (count($tokens) > self::MIN_SUGGESTION_TOKENS) {
            $last = $tokens[array_key_last($tokens)];

            if (! in_array($last, self::DESCRIPTOR_TOKENS, true)) {
                break;
            }

            array_pop($tokens);
        }

        return $tokens;
    }

    private function isUntitled(string $title): bool
    {
        return strcasecmp(trim($title), 'untitled') === 0;
    }
}
