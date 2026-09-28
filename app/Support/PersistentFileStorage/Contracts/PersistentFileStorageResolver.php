<?php

namespace App\Support\PersistentFileStorage\Contracts;

use App\Support\PersistentFileStorage\Contracts\PersistentFileStorage as PersistentFileStorageContract;

/**
 * Resolves an installation-configured target name to a PersistentFileStorage provider.
 *
 * Maps trusted config target names only. Does not resolve tenant ownership
 * records or Eloquent models.
 */
interface PersistentFileStorageResolver
{
    public function resolve(string $configTarget): PersistentFileStorageContract;

    public function resolveDefault(): PersistentFileStorageContract;
}
