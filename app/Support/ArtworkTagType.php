<?php

namespace App\Support;

class ArtworkTagType
{
    public const STYLE = 'style';

    public const SUBJECT = 'subject';

    public const GENERAL = 'general';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::STYLE,
            self::SUBJECT,
            self::GENERAL,
        ];
    }

    public static function isValid(?string $type): bool
    {
        return is_string($type) && in_array($type, self::all(), true);
    }

    public static function normalize(?string $type): string
    {
        $type = is_string($type) ? strtolower(trim($type)) : '';

        return self::isValid($type) ? $type : self::GENERAL;
    }

    public static function label(string $type): string
    {
        return match (self::normalize($type)) {
            self::STYLE => 'Style',
            self::SUBJECT => 'Subject',
            default => 'General',
        };
    }
}
