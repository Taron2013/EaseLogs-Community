<?php

namespace App\Support\PersistentFileStorage;

use InvalidArgumentException;

/**
 * Builds Studio/installation-scoped logical keys without provider knowledge.
 *
 * Pattern:
 * studios/{tenant_uuid}/{storage_domain}/{record_uuid}/{file_uuid}/{variant}
 */
final class StorageKeyFactory
{
    public function make(
        TenantScope $tenant,
        StorageDomain $domain,
        string $recordUuid,
        string $fileUuid,
        string $variant,
    ): StorageKey {
        $recordUuid = $this->normalizeUuid($recordUuid, 'record');
        $fileUuid = $this->normalizeUuid($fileUuid, 'file');
        $variant = $this->normalizeVariant($variant);

        return StorageKey::fromString(implode('/', [
            $tenant->keyPrefix(),
            $domain->value,
            $recordUuid,
            $fileUuid,
            $variant,
        ]));
    }

    /**
     * Convenience for the common "original" object variant with an extension.
     */
    public function makeOriginal(
        TenantScope $tenant,
        StorageDomain $domain,
        string $recordUuid,
        string $fileUuid,
        string $extension,
    ): StorageKey {
        $extension = strtolower(ltrim(trim($extension), '.'));

        if ($extension === '' || ! preg_match('/^[a-z0-9]+$/', $extension)) {
            throw new InvalidArgumentException('Extension must be a non-empty alphanumeric suffix.');
        }

        return $this->make($tenant, $domain, $recordUuid, $fileUuid, 'original.'.$extension);
    }

    private function normalizeUuid(string $uuid, string $label): string
    {
        $normalized = strtolower(trim($uuid));

        if ($normalized === '' || ! preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $normalized,
        )) {
            throw new InvalidArgumentException("Invalid {$label} UUID for storage key.");
        }

        return $normalized;
    }

    private function normalizeVariant(string $variant): string
    {
        $variant = trim(str_replace('\\', '/', $variant));

        if ($variant === '' || str_contains($variant, '/') || str_contains($variant, '..')) {
            throw new InvalidArgumentException('Variant must be a single path segment.');
        }

        if (! preg_match('/^[A-Za-z0-9._~-]+$/', $variant)) {
            throw new InvalidArgumentException('Variant contains unsupported characters.');
        }

        return $variant;
    }
}
