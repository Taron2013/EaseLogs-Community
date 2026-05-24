<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Artwork;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SKUGenerator
{
    public const DEFAULT_INVENTORY_PATTERN = 'ART-{YYYY}-{####}';

    public const DEFAULT_SKU_PATTERN = '{YYYY}#[N]';

    public const DEFAULT_STARTING_SEQUENCE = 1;

    private const INVENTORY_SEQUENCE_TOKENS = [
        '{######}' => 6,
        '{#####}' => 5,
        '{####}' => 4,
    ];

    private const SKU_SEQUENCE_TOKENS = [
        '[NNNN]' => 4,
        '[NNN]' => 3,
        '[NN]' => 2,
        '[N]' => 1,
    ];

    public function generateInventoryCode(array $context = []): string
    {
        $pattern = $this->inventoryPattern();
        $sequence = $this->nextSequence('inventory_code', $pattern, $context);

        return $this->generateUnique('inventory_code', $pattern, $sequence, $context);
    }

    public function generateArtworkSKU(array $context = []): string
    {
        $pattern = $this->skuPattern();
        $context = $this->skuContext($context);
        $sequence = $this->nextSequence('sku', $pattern, $context);

        return $this->generateUnique('sku', $pattern, $sequence, $context);
    }

    public function parsePattern(string $pattern, int $sequence, array $context = []): string
    {
        $date = $this->resolvePatternDate($pattern, $context);
        $result = $pattern;

        $result = $this->replaceMetadataTokens($result, $context);
        $result = $this->replaceDateTokens($result, $date);
        $result = $this->replaceSequenceTokens($result, $sequence);

        return $result;
    }

    public function nextSequence(string $column, string $pattern, array $context = []): int
    {
        $startingSequence = $this->startingSequence();

        if ($column === 'sku') {
            return $this->nextSkuSequence($pattern, $context, $startingSequence);
        }

        return $this->nextInventorySequence($pattern, $context, $startingSequence);
    }

    private function generateUnique(string $column, string $pattern, int $sequence, array $context): string
    {
        $attempts = 0;

        while ($attempts < 1000) {
            $value = $this->parsePattern($pattern, $sequence, $context);

            if (! $this->valueExists($column, $value, $context)) {
                return $value;
            }

            $sequence++;
            $attempts++;
        }

        throw new \RuntimeException("Unable to generate a unique {$column} after multiple attempts.");
    }

    private function valueExists(string $column, string $value, array $context): bool
    {
        $query = Artwork::query()->where($column, $value);

        if (isset($context['exclude_artwork_id'])) {
            $query->where('id', '!=', $context['exclude_artwork_id']);
        }

        return $query->exists();
    }

    private function nextInventorySequence(string $pattern, array $context, int $startingSequence): int
    {
        $date = $this->resolveReferenceDate($context);
        $prefix = $this->inventoryPrefix($pattern, $date, $context);
        $max = 0;

        $query = Artwork::query()->where('inventory_code', 'like', $prefix.'%');

        if (isset($context['exclude_artwork_id'])) {
            $query->where('id', '!=', $context['exclude_artwork_id']);
        }

        foreach ($query->pluck('inventory_code') as $inventoryCode) {
            $suffix = substr((string) $inventoryCode, strlen($prefix));

            if ($suffix !== '' && ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        if ($max === 0) {
            return max($startingSequence, 1);
        }

        return max($startingSequence, $max + 1);
    }

    private function nextSkuSequence(string $pattern, array $context, int $startingSequence): int
    {
        $year = $this->resolveSkuYear($context);
        $prefix = $this->skuPrefix($pattern, $year, $context);
        $max = 0;

        $query = Artwork::query()
            ->whereNotNull('sku')
            ->where('sku', 'like', $prefix.'%');

        if (isset($context['exclude_artwork_id'])) {
            $query->where('id', '!=', $context['exclude_artwork_id']);
        }

        foreach ($query->pluck('sku') as $sku) {
            $suffix = substr((string) $sku, strlen($prefix));

            if ($suffix !== '' && ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        $completedInYear = Artwork::query()
            ->whereNotNull('finished_date')
            ->whereYear('finished_date', $year);

        if (isset($context['exclude_artwork_id'])) {
            $completedInYear->where('id', '!=', $context['exclude_artwork_id']);
        }

        $completedCount = $completedInYear->count();
        $candidate = max($startingSequence, $completedCount + 1, $max + 1);

        return $candidate > 0 ? $candidate : $startingSequence;
    }

    private function inventoryPrefix(string $pattern, CarbonInterface $date, array $context): string
    {
        $prefixPattern = $pattern;

        foreach (array_keys(self::INVENTORY_SEQUENCE_TOKENS) as $token) {
            $prefixPattern = str_replace($token, '', $prefixPattern);
        }

        return $this->replaceDateTokens($this->replaceMetadataTokens($prefixPattern, $context), $date);
    }

    private function skuPrefix(string $pattern, int $year, array $context): string
    {
        $prefixPattern = $pattern;

        foreach (array_keys(self::SKU_SEQUENCE_TOKENS) as $token) {
            $prefixPattern = str_replace($token, '', $prefixPattern);
        }

        $date = Carbon::create($year, 1, 1);
        $context = array_merge($context, [
            'finished_date' => $context['finished_date'] ?? $date,
            'reference_date' => $date,
        ]);

        return $this->replaceDateTokens($this->replaceMetadataTokens($prefixPattern, $context), $date);
    }

    private function replaceMetadataTokens(string $pattern, array $context): string
    {
        $replacements = [
            '{MEDIUM}' => $this->sanitizeToken(
                $context['medium'] ?? null,
                'UNK'
            ),
            '{CATEGORY}' => $this->sanitizeToken(
                $context['category'] ?? null,
                'GEN'
            ),
            '{USER}' => $this->sanitizeToken(
                $this->resolveUserToken($context),
                'USER'
            ),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $pattern);
    }

    private function replaceDateTokens(string $pattern, CarbonInterface $date): string
    {
        $replacements = [
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{DD}' => $date->format('d'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $pattern);
    }

    private function replaceSequenceTokens(string $pattern, int $sequence): string
    {
        $result = $pattern;

        foreach (self::INVENTORY_SEQUENCE_TOKENS as $token => $length) {
            if (str_contains($result, $token)) {
                $result = str_replace($token, str_pad((string) $sequence, $length, '0', STR_PAD_LEFT), $result);
            }
        }

        foreach (self::SKU_SEQUENCE_TOKENS as $token => $minWidth) {
            if (str_contains($result, $token)) {
                $result = str_replace($token, $this->formatSkuSequence($sequence, $minWidth), $result);
            }
        }

        return $result;
    }

    private function formatSkuSequence(int $sequence, int $minWidth): string
    {
        if ($minWidth <= 1) {
            return (string) $sequence;
        }

        return str_pad((string) $sequence, $minWidth, '0', STR_PAD_LEFT);
    }

    private function sanitizeToken(?string $value, string $fallback): string
    {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/\s+/', '-', $value) ?? '';
        $value = preg_replace('/[^A-Z0-9\-]/', '', $value) ?? '';

        return $value !== '' ? $value : $fallback;
    }

    private function resolveUserToken(array $context): ?string
    {
        if (isset($context['user_name'])) {
            return (string) $context['user_name'];
        }

        if (isset($context['user']) && is_object($context['user']) && isset($context['user']->name)) {
            return (string) $context['user']->name;
        }

        return null;
    }

    private function resolvePatternDate(string $pattern, array $context): CarbonInterface
    {
        foreach (array_keys(self::SKU_SEQUENCE_TOKENS) as $token) {
            if (str_contains($pattern, $token)) {
                return $this->resolveSkuDate($context);
            }
        }

        return $this->resolveReferenceDate($context);
    }

    private function resolveReferenceDate(array $context): CarbonInterface
    {
        if (isset($context['reference_date'])) {
            return Carbon::parse($context['reference_date']);
        }

        if (isset($context['finished_date'])) {
            return Carbon::parse($context['finished_date']);
        }

        return Carbon::now();
    }

    private function resolveSkuDate(array $context): CarbonInterface
    {
        if (isset($context['finished_date']) && $context['finished_date'] !== null) {
            return Carbon::parse($context['finished_date']);
        }

        return Carbon::now();
    }

    private function resolveSkuYear(array $context): int
    {
        if (isset($context['finished_date']) && $context['finished_date'] !== null) {
            return (int) Carbon::parse($context['finished_date'])->format('Y');
        }

        if (isset($context['reference_date'])) {
            return (int) Carbon::parse($context['reference_date'])->format('Y');
        }

        return (int) Carbon::now()->format('Y');
    }

    private function skuContext(array $context): array
    {
        if (! array_key_exists('finished_date', $context)) {
            $context['finished_date'] = Carbon::now();
        }

        return $context;
    }

    private function inventoryPattern(): string
    {
        return $this->setting('default_inventory_pattern', self::DEFAULT_INVENTORY_PATTERN)
            ?? self::DEFAULT_INVENTORY_PATTERN;
    }

    private function skuPattern(): string
    {
        return $this->setting('default_sku_pattern', self::DEFAULT_SKU_PATTERN)
            ?? self::DEFAULT_SKU_PATTERN;
    }

    private function startingSequence(): int
    {
        $value = $this->setting('starting_sequence');

        if ($value === null || $value === '') {
            return self::DEFAULT_STARTING_SEQUENCE;
        }

        return max(1, (int) $value);
    }

    private function setting(string $key, ?string $default = null): ?string
    {
        $value = AppSetting::query()
            ->where('setting_key', $key)
            ->value('setting_value');

        return $value ?? $default;
    }
}
