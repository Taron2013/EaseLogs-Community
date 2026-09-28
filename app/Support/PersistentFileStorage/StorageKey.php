<?php

namespace App\Support\PersistentFileStorage;

use App\Support\PersistentFileStorage\Exceptions\InvalidStorageKeyException;
use Stringable;

/**
 * Provider-neutral logical object key. Never an absolute path or cloud URL.
 *
 * Immutable value object. Windows-style separators are normalized to `/`.
 * Traversal (`..`), empty segments, null bytes, control characters, absolute
 * paths, and URLs are rejected.
 */
final readonly class StorageKey implements Stringable
{
    public function __construct(
        private string $key,
    ) {
        $normalized = self::normalize($this->key);

        if ($normalized !== $this->key) {
            throw new InvalidStorageKeyException(
                'StorageKey must be a normalized logical key without leading slashes or traversal segments.',
            );
        }
    }

    public static function fromString(string $key): self
    {
        return new self(self::normalize($key));
    }

    public function toString(): string
    {
        return $this->key;
    }

    public function __toString(): string
    {
        return $this->key;
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key;
    }

    /**
     * @throws InvalidStorageKeyException
     */
    public static function normalize(string $key): string
    {
        // Reject before silent truncation at null bytes.
        if (str_contains($key, "\0") || preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new InvalidStorageKeyException('Storage key must not contain null or control characters.');
        }

        // Policy: normalize Windows separators to logical `/` segments.
        $key = str_replace('\\', '/', trim($key));

        if ($key === '') {
            throw new InvalidStorageKeyException('Storage key must be a non-empty logical path.');
        }

        if (str_starts_with($key, '/') || preg_match('#^[a-zA-Z]:/#', $key) === 1) {
            throw new InvalidStorageKeyException('Storage key must not be an absolute path.');
        }

        if (str_contains($key, '://')) {
            throw new InvalidStorageKeyException('Storage key must not be a URL.');
        }

        $segments = explode('/', $key);
        $normalized = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new InvalidStorageKeyException('Storage key must not contain path traversal segments.');
            }

            // ASCII-safe segments only — rejects unexpected Unicode / normalization tricks.
            if (! preg_match('/^[A-Za-z0-9._~-]+$/', $segment)) {
                throw new InvalidStorageKeyException(
                    'Storage key segments may only contain letters, digits, and . _ ~ - characters.',
                );
            }

            $normalized[] = $segment;
        }

        if ($normalized === []) {
            throw new InvalidStorageKeyException('Storage key must contain at least one segment.');
        }

        return implode('/', $normalized);
    }
}
