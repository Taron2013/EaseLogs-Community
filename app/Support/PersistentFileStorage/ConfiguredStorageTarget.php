<?php

namespace App\Support\PersistentFileStorage;

/**
 * A named target from the installation configuration registry.
 */
final readonly class ConfiguredStorageTarget
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $name,
        public string $driver,
        public array $options = [],
    ) {}

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
