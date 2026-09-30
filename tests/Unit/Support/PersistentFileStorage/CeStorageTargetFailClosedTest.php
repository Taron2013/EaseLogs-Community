<?php

namespace Tests\Unit\Support\PersistentFileStorage;

use App\Support\PersistentFileStorage\Exceptions\StorageTargetNotFoundException;
use App\Support\PersistentFileStorage\Local\LocalPersistentFileStorage;
use App\Support\PersistentFileStorage\PersistentFileStorageResolver;
use App\Support\PersistentFileStorage\StorageTargetRegistry;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CeStorageTargetFailClosedTest extends TestCase
{
    public function test_shipped_default_resolves_local_persistent(): void
    {
        $registry = $this->app->make(StorageTargetRegistry::class);
        $resolver = $this->app->make(PersistentFileStorageResolver::class);

        $this->assertSame('local_persistent', $registry->defaultTargetName());

        $storage = $resolver->resolveDefault();

        $this->assertInstanceOf(LocalPersistentFileStorage::class, $storage);
        $this->assertSame('local_persistent', $storage->configTarget());
        $this->assertSame('local', $storage->driver());
    }

    public function test_missing_default_target_fails_closed(): void
    {
        $config = Config::get('easelogs_storage');
        unset($config['default_target']);
        Config::set('easelogs_storage', $config);

        $registry = $this->app->make(StorageTargetRegistry::class);

        $this->expectException(StorageTargetNotFoundException::class);
        $this->expectExceptionMessage('Configured storage default_target is missing.');

        $registry->defaultTargetName();
    }

    public function test_null_default_target_fails_closed(): void
    {
        Config::set('easelogs_storage.default_target', null);

        $registry = $this->app->make(StorageTargetRegistry::class);

        $this->expectException(StorageTargetNotFoundException::class);
        $this->expectExceptionMessage('Configured storage default_target is missing.');

        $registry->defaultTargetName();
    }

    public function test_empty_string_default_target_fails_closed(): void
    {
        Config::set('easelogs_storage.default_target', '');

        $registry = $this->app->make(StorageTargetRegistry::class);

        $this->expectException(StorageTargetNotFoundException::class);
        $this->expectExceptionMessage('Configured storage default_target is blank.');

        $registry->defaultTargetName();
    }

    public function test_whitespace_only_default_target_fails_closed(): void
    {
        Config::set('easelogs_storage.default_target', " \t\n");

        $registry = $this->app->make(StorageTargetRegistry::class);

        $this->expectException(StorageTargetNotFoundException::class);
        $this->expectExceptionMessage('Configured storage default_target is blank.');

        $registry->defaultTargetName();
    }

    #[DataProvider('invalidNonStringDefaultTargets')]
    public function test_non_string_default_target_fails_closed(mixed $value): void
    {
        Config::set('easelogs_storage.default_target', $value);

        $registry = $this->app->make(StorageTargetRegistry::class);

        $this->expectException(StorageTargetNotFoundException::class);
        $this->expectExceptionMessage('Configured storage default_target must be a non-empty string.');

        $registry->defaultTargetName();
    }

    /**
     * @return list<array{0: mixed}>
     */
    public static function invalidNonStringDefaultTargets(): array
    {
        return [
            [42],
            [true],
            [['local_persistent']],
        ];
    }

    public function test_configured_nonexistent_default_target_fails_closed_via_default_resolution(): void
    {
        Config::set('easelogs_storage.default_target', 'does_not_exist');

        $registry = $this->app->make(StorageTargetRegistry::class);

        $this->assertSame('does_not_exist', $registry->defaultTargetName());

        $this->expectException(StorageTargetNotFoundException::class);
        $this->expectExceptionMessage('Configured storage target [does_not_exist] is not defined.');

        $registry->default();
    }

    #[DataProvider('laravelFilesystemDiskNames')]
    public function test_laravel_filesystem_disk_names_are_not_pfs_targets(string $diskName): void
    {
        $resolver = $this->app->make(PersistentFileStorageResolver::class);

        $this->expectException(StorageTargetNotFoundException::class);

        $resolver->resolve($diskName);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function laravelFilesystemDiskNames(): array
    {
        return [
            ['public'],
            ['local'],
            ['s3'],
        ];
    }
}
