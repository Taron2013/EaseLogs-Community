<?php

namespace App\Support\PersistentFileStorage;

use App\Support\PersistentFileStorage\Contracts\PersistentFileStorage as PersistentFileStorageContract;
use App\Support\PersistentFileStorage\Contracts\PersistentFileStorageResolver as PersistentFileStorageResolverContract;
use App\Support\PersistentFileStorage\Exceptions\UnsupportedStorageDriverException;
use App\Support\PersistentFileStorage\Local\LocalPersistentFileStorage;

/**
 * Builds PersistentFileStorage providers from configured target names.
 *
 * Does not resolve tenant ownership records; callers pass a config target name.
 */
final class PersistentFileStorageResolver implements PersistentFileStorageResolverContract
{
    public function __construct(
        private readonly StorageTargetRegistry $registry,
    ) {}

    public function resolve(string $configTarget): PersistentFileStorageContract
    {
        $target = $this->registry->get($configTarget);

        return $this->makeProvider($target);
    }

    public function resolveDefault(): PersistentFileStorageContract
    {
        return $this->makeProvider($this->registry->default());
    }

    private function makeProvider(ConfiguredStorageTarget $target): PersistentFileStorageContract
    {
        return match ($target->driver) {
            'local' => LocalPersistentFileStorage::fromConfiguredTarget($target),
            default => throw new UnsupportedStorageDriverException(
                "Storage driver [{$target->driver}] for target [{$target->name}] is not supported.",
            ),
        };
    }
}
