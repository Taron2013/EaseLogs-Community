<?php

namespace App\Support\ArtworkPhotoBulkImport;

use App\Models\Artwork;
use Illuminate\Database\Eloquent\Collection;

final class PartialTitleMatcher
{
    public const MIN_CONFIDENCE = 0.40;

    public const NEAR_TIE_DELTA = 0.05;

    private const PREFIX_SCORE = 0.95;

    private const PHRASE_CONTAINMENT_SCORE = 0.90;

    private const ORDERED_SUBSET_SCORE = 0.85;

    private const MEANINGFUL_OVERLAP_MAX = 0.79;

    /**
     * @var list<string>
     */
    private const FILLER_WORDS = [
        'the', 'a', 'an', 'in', 'on', 'of', 'for', 'at', 'to', 'and', 'or', 'with',
    ];

    /**
     * @var list<string>
     */
    private const EXPORT_SUFFIXES = [
        'final', 'edit', 'edited', 'crop', 'cropped', 'image', 'photo', 'scan',
        'copy', 'front', 'back', 'side', 'detail', 'closeup',
    ];

    /**
     * @param  Collection<int, Artwork>  $artworks
     * @return array{
     *     artwork: ?Artwork,
     *     artworks: Collection<int, Artwork>,
     *     confidence: ?float,
     *     ambiguous: bool,
     *     error: ?string
     * }
     */
    public function findBestMatch(string $candidate, Collection $artworks): array
    {
        $candidateNorm = $this->normalizePhrase($candidate);

        if ($candidateNorm === '' || $this->isUntitled($candidate)) {
            return $this->noMatch('Title candidate has no meaningful words for partial matching.');
        }

        $scored = [];

        foreach ($artworks as $artwork) {
            if ($this->isUntitled($artwork->title)) {
                continue;
            }

            $score = $this->score($candidate, $artwork->title);

            if ($score < self::MIN_CONFIDENCE) {
                continue;
            }

            $scored[] = [
                'artwork' => $artwork,
                'score' => $score,
            ];
        }

        if ($scored === []) {
            return $this->noMatch("No artwork found with a partial title match for \"{$candidate}\".");
        }

        usort($scored, function (array $left, array $right): int {
            $scoreCompare = $right['score'] <=> $left['score'];

            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return $left['artwork']->id <=> $right['artwork']->id;
        });

        $best = $scored[0];
        $secondScore = $scored[1]['score'] ?? null;

        if ($secondScore !== null && ($best['score'] - $secondScore) < self::NEAR_TIE_DELTA) {
            $ties = array_values(array_filter(
                $scored,
                fn (array $row): bool => ($best['score'] - $row['score']) < self::NEAR_TIE_DELTA,
            ));

            return [
                'artwork' => null,
                'artworks' => new Collection(array_map(
                    fn (array $row): Artwork => $row['artwork'],
                    $ties,
                )),
                'confidence' => $best['score'],
                'ambiguous' => true,
                'error' => "Multiple artworks partially match title \"{$candidate}\".",
            ];
        }

        return [
            'artwork' => $best['artwork'],
            'artworks' => new Collection([$best['artwork']]),
            'confidence' => $best['score'],
            'ambiguous' => false,
            'error' => null,
        ];
    }

    public function score(string $candidate, string $artworkTitle): float
    {
        $candidateNorm = $this->normalizePhrase($candidate);
        $artworkNorm = $this->normalizePhrase($artworkTitle);

        if ($candidateNorm === '' || $artworkNorm === '') {
            return 0.0;
        }

        if (str_starts_with($artworkNorm, $candidateNorm)) {
            return self::PREFIX_SCORE;
        }

        if (str_contains($artworkNorm, $candidateNorm)) {
            return self::PHRASE_CONTAINMENT_SCORE;
        }

        $candidateTokens = $this->allTokens($candidateNorm);
        $artworkTokens = $this->allTokens($artworkNorm);

        if ($this->isOrderedTokenSubset($candidateTokens, $artworkTokens)) {
            return self::ORDERED_SUBSET_SCORE;
        }

        return min(
            self::MEANINGFUL_OVERLAP_MAX,
            $this->meaningfulTokenOverlapScore(
                $this->meaningfulTokens($candidate),
                $this->meaningfulTokens($artworkTitle),
            ),
        );
    }

    /**
     * @return list<string>
     */
    public function meaningfulTokens(string $value): array
    {
        $normalized = $this->normalizePhrase($value);

        if ($normalized === '' || $this->isUntitled($normalized)) {
            return [];
        }

        $tokens = $this->allTokens($normalized);
        $tokens = $this->stripExportSuffixTokens($tokens);

        return array_values(array_filter(
            $tokens,
            fn (string $token): bool => $token !== '' && ! in_array($token, self::FILLER_WORDS, true),
        ));
    }

    /**
     * @param  list<string>  $candidateTokens
     * @param  list<string>  $artworkTokens
     */
    private function meaningfulTokenOverlapScore(array $candidateTokens, array $artworkTokens): float
    {
        if ($candidateTokens === [] || $artworkTokens === []) {
            return 0.0;
        }

        $overlap = array_values(array_intersect($candidateTokens, $artworkTokens));
        $overlapCount = count($overlap);

        if ($overlapCount === 0) {
            return 0.0;
        }

        $candidateCount = count($candidateTokens);
        $coverage = $overlapCount / $candidateCount;
        $orderScore = $this->orderScore($candidateTokens, $artworkTokens);

        $candidatePhrase = implode(' ', $candidateTokens);
        $artworkPhrase = implode(' ', $artworkTokens);
        $containmentBonus = str_contains($artworkPhrase, $candidatePhrase) ? 0.20 : 0.0;

        $extraWords = count($artworkTokens) - $overlapCount;
        $extraPenalty = min(0.25, $extraWords * 0.05);

        $missingWords = $candidateCount - $overlapCount;
        $missingPenalty = min(0.45, $missingWords * 0.15);

        $score = ($coverage * 0.45) + ($orderScore * 0.35) + $containmentBonus - $extraPenalty - $missingPenalty;

        return max(0.0, min(1.0, $score));
    }

    /**
     * @param  list<string>  $subsetTokens
     * @param  list<string>  $haystackTokens
     */
    private function isOrderedTokenSubset(array $subsetTokens, array $haystackTokens): bool
    {
        if ($subsetTokens === []) {
            return false;
        }

        $searchFrom = 0;

        foreach ($subsetTokens as $token) {
            $foundAt = null;

            for ($index = $searchFrom; $index < count($haystackTokens); $index++) {
                if ($haystackTokens[$index] === $token) {
                    $foundAt = $index;

                    break;
                }
            }

            if ($foundAt === null) {
                return false;
            }

            $searchFrom = $foundAt + 1;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function allTokens(string $normalizedPhrase): array
    {
        if ($normalizedPhrase === '') {
            return [];
        }

        return array_values(array_filter(explode(' ', $normalizedPhrase), fn (string $token): bool => $token !== ''));
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function stripExportSuffixTokens(array $tokens): array
    {
        while ($tokens !== []) {
            $last = $tokens[array_key_last($tokens)];

            if (in_array($last, self::EXPORT_SUFFIXES, true) || preg_match('/^photo\d+$/', $last) === 1) {
                array_pop($tokens);

                continue;
            }

            break;
        }

        return $tokens;
    }

    /**
     * @param  list<string>  $candidateTokens
     * @param  list<string>  $artworkTokens
     */
    private function orderScore(array $candidateTokens, array $artworkTokens): float
    {
        $artworkPositions = [];

        foreach ($artworkTokens as $index => $token) {
            if (! array_key_exists($token, $artworkPositions)) {
                $artworkPositions[$token] = $index;
            }
        }

        $matchedInOrder = 0;
        $lastPosition = -1;

        foreach ($candidateTokens as $token) {
            if (! array_key_exists($token, $artworkPositions)) {
                continue;
            }

            $position = $artworkPositions[$token];

            if ($position > $lastPosition) {
                $matchedInOrder++;
                $lastPosition = $position;
            }
        }

        $overlapCount = count(array_intersect($candidateTokens, $artworkTokens));

        return $overlapCount === 0 ? 0.0 : $matchedInOrder / $overlapCount;
    }

    private function normalizePhrase(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return $value;
    }

    private function isUntitled(string $title): bool
    {
        return strcasecmp(trim($title), 'untitled') === 0;
    }

    /**
     * @return array{
     *     artwork: null,
     *     artworks: Collection<int, Artwork>,
     *     confidence: null,
     *     ambiguous: false,
     *     error: string
     * }
     */
    private function noMatch(string $error): array
    {
        return [
            'artwork' => null,
            'artworks' => new Collection,
            'confidence' => null,
            'ambiguous' => false,
            'error' => $error,
        ];
    }
}
