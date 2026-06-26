<?php

namespace App\Support\ArtworkPhotoBulkImport;

final class FilenameTitleParser
{
    /**
     * Trailing artwork export identifiers, e.g. -2022#11, -2022#9B, or -2023#101-11272023
     */
    private const ARTWORK_IDENTIFIER_SUFFIX = '/-\d{4}\s*#\s*[\da-z]+(?:-\d{1,8})?$/i';

    /**
     * @var list<string>
     */
    private const EXPORT_SUFFIXES = [
        'final',
        'edit',
        'edited',
        'crop',
        'cropped',
        'image',
        'photo',
        'scan',
        'copy',
        'front',
        'back',
        'side',
        'detail',
        'closeup',
    ];

    public function parse(string $filepath): ?string
    {
        $stem = pathinfo(basename($filepath), PATHINFO_FILENAME);
        $stem = $this->stripTrailingMetadataFromStem($stem);
        $stem = str_replace(['_', '-'], ' ', $stem);
        $stem = preg_replace('/\s+/u', ' ', trim($stem)) ?? '';
        $stem = $this->stripTrailingNoiseTokens($stem);
        $stem = $this->stripExportSuffixTokens($stem);

        if ($stem === '' || strcasecmp($stem, 'untitled') === 0) {
            return null;
        }

        return $stem;
    }

    private function stripTrailingMetadataFromStem(string $stem): string
    {
        $previous = null;

        while ($previous !== $stem) {
            $previous = $stem;

            $stem = preg_replace('/(?:^|[\s_-])(\d{1,3})\s*x\s*(\d{1,3})\s*$/ix', '', $stem) ?? $stem;
            $stem = preg_replace('/\s+\d{1,3}\s*x\s*\d{1,3}\s*$/ix', '', $stem) ?? $stem;
            $stem = preg_replace('/-\d{8}$/', '', $stem) ?? $stem;
            $stem = preg_replace('/-\d{1,2}-\d{1,2}-\d{4}$/', '', $stem) ?? $stem;
            $stem = preg_replace('/-\d{4}\s*#\s*[\da-z]+\s*$/i', '', $stem) ?? $stem;
            $stem = preg_replace(self::ARTWORK_IDENTIFIER_SUFFIX, '', $stem) ?? $stem;
            $stem = rtrim($stem, " \t\n\r\0\x0B-_");
        }

        return $stem;
    }

    private function stripTrailingNoiseTokens(string $stem): string
    {
        if ($stem === '') {
            return '';
        }

        $tokens = explode(' ', $stem);

        while ($tokens !== []) {
            if ($this->popTrailingDimensionTokens($tokens)) {
                continue;
            }

            if ($this->popTrailingSkuTokens($tokens)) {
                continue;
            }

            $last = $tokens[array_key_last($tokens)];

            if ($this->isDateToken($last)) {
                array_pop($tokens);

                continue;
            }

            if ($last === '#') {
                array_pop($tokens);

                continue;
            }

            break;
        }

        return trim(implode(' ', $tokens));
    }

    /**
     * @param  list<string>  $tokens
     */
    private function popTrailingDimensionTokens(array &$tokens): bool
    {
        $count = count($tokens);

        if ($count >= 3
            && strtolower($tokens[$count - 2]) === 'x'
            && ctype_digit($tokens[$count - 3])
            && ctype_digit($tokens[$count - 1])) {
            array_splice($tokens, -3);

            return true;
        }

        if ($count >= 1 && preg_match('/^\d{1,3}x\d{1,3}$/i', $tokens[$count - 1]) === 1) {
            array_pop($tokens);

            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function popTrailingSkuTokens(array &$tokens): bool
    {
        $count = count($tokens);

        if ($count >= 1 && $this->isSkuToken($tokens[$count - 1])) {
            array_pop($tokens);

            return true;
        }

        if ($count >= 3
            && preg_match('/^\d{4}$/', $tokens[$count - 3]) === 1
            && $tokens[$count - 2] === '#'
            && preg_match('/^[\da-z]+$/i', $tokens[$count - 1]) === 1) {
            array_splice($tokens, -3);

            return true;
        }

        if ($count >= 4
            && preg_match('/^\d{4}$/', $tokens[$count - 4]) === 1
            && $tokens[$count - 3] === '#'
            && preg_match('/^[\da-z]+$/i', $tokens[$count - 2]) === 1
            && strtolower($tokens[$count - 1]) === 'x') {
            array_splice($tokens, -4);

            return true;
        }

        return false;
    }

    private function isSkuToken(string $token): bool
    {
        $normalized = strtolower(preg_replace('/\s+/', '', $token) ?? $token);

        return preg_match('/^\d{4}#[\da-z]+$/i', $normalized) === 1;
    }

    private function isDateToken(string $token): bool
    {
        return preg_match('/^\d{8}$/', $token) === 1
            || preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $token) === 1;
    }

    private function stripExportSuffixTokens(string $stem): string
    {
        if ($stem === '') {
            return '';
        }

        $tokens = explode(' ', $stem);

        while ($tokens !== []) {
            $last = strtolower($tokens[array_key_last($tokens)]);

            if (in_array($last, self::EXPORT_SUFFIXES, true) || preg_match('/^photo\d+$/', $last) === 1) {
                array_pop($tokens);

                continue;
            }

            break;
        }

        return trim(implode(' ', $tokens));
    }
}
