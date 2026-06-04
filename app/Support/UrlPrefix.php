<?php

namespace App\Support;

final class UrlPrefix
{
    public static function path(): string
    {
        return (string) config('easelogs.url_prefix', '');
    }

    public static function configured(): bool
    {
        return self::path() !== '';
    }

    public static function normalize(?string $prefix): string
    {
        $prefix = trim((string) $prefix);

        return trim($prefix, '/');
    }
}
