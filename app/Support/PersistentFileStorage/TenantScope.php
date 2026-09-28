<?php

namespace App\Support\PersistentFileStorage;

use InvalidArgumentException;

/**
 * Opaque tenant segment for logical storage keys.
 *
 * Pro uses a Studio UUID. Community Edition may later use an installation UUID.
 * This value object must not depend on Studio Eloquent models.
 */
final readonly class TenantScope
{
    private function __construct(
        public string $uuid,
    ) {}

    public static function fromUuid(string $uuid): self
    {
        $normalized = strtolower(trim($uuid));

        if ($normalized === '' || ! self::isUuid($normalized)) {
            throw new InvalidArgumentException('TenantScope requires a valid UUID string.');
        }

        return new self($normalized);
    }

    /**
     * Prefix used in logical keys: studios/{uuid}
     *
     * Named "studios" for cross-edition key stability; CE uses a synthetic
     * installation UUID in the same segment.
     */
    public function keyPrefix(): string
    {
        return 'studios/'.$this->uuid;
    }

    private static function isUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $value,
        );
    }
}
