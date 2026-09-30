<?php

namespace App\Support\PersistentFileStorage;

use App\Support\PersistentFileStorage\Exceptions\StorageTargetNotFoundException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Reads trusted installation storage targets from config/easelogs_storage.php.
 */
final class StorageTargetRegistry
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function defaultTargetName(): string
    {
        if (! $this->config->has('easelogs_storage.default_target')) {
            throw new StorageTargetNotFoundException(
                'Configured storage default_target is missing.',
            );
        }

        $raw = $this->config->get('easelogs_storage.default_target');

        if ($raw === null) {
            throw new StorageTargetNotFoundException(
                'Configured storage default_target is missing.',
            );
        }

        if (! is_string($raw)) {
            throw new StorageTargetNotFoundException(
                'Configured storage default_target must be a non-empty string.',
            );
        }

        $name = trim($raw);

        if ($name === '') {
            throw new StorageTargetNotFoundException(
                'Configured storage default_target is blank.',
            );
        }

        return $name;
    }

    public function has(string $name): bool
    {
        return is_array($this->config->get('easelogs_storage.targets.'.$name));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $targets = $this->config->get('easelogs_storage.targets', []);

        if (! is_array($targets)) {
            return [];
        }

        return array_keys($targets);
    }

    public function get(string $name): ConfiguredStorageTarget
    {
        $options = $this->config->get('easelogs_storage.targets.'.$name);

        if (! is_array($options)) {
            throw new StorageTargetNotFoundException(
                "Configured storage target [{$name}] is not defined.",
            );
        }

        $driver = (string) ($options['driver'] ?? '');

        if ($driver === '') {
            throw new StorageTargetNotFoundException(
                "Configured storage target [{$name}] is missing a driver.",
            );
        }

        return new ConfiguredStorageTarget(
            name: $name,
            driver: $driver,
            options: $options,
        );
    }

    public function default(): ConfiguredStorageTarget
    {
        return $this->get($this->defaultTargetName());
    }
}
