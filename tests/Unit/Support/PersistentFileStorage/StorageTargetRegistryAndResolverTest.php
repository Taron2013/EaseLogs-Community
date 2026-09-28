<?php

namespace Tests\Unit\Support\PersistentFileStorage;

use App\Support\PersistentFileStorage\Exceptions\StorageTargetNotFoundException;
use App\Support\PersistentFileStorage\Exceptions\UnsupportedStorageDriverException;
use App\Support\PersistentFileStorage\Local\LocalPersistentFileStorage;
use App\Support\PersistentFileStorage\PersistentFileStorageResolver;
use App\Support\PersistentFileStorage\StorageTargetRegistry;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StorageTargetRegistryAndResolverTest extends TestCase
{
    public function test_registry_resolves_default_local_persistent_target(): void
    {
        $registry = $this->app->make(StorageTargetRegistry::class);

        $this->assertSame('local_persistent', $registry->defaultTargetName());
        $this->assertTrue($registry->has('local_persistent'));

        $target = $registry->default();

        $this->assertSame('local_persistent', $target->name);
        $this->assertSame('local', $target->driver);
        $this->assertNotSame('', (string) $target->option('root'));
    }

    public function test_registry_throws_for_unknown_target(): void
    {
        $registry = $this->app->make(StorageTargetRegistry::class);

        $this->expectException(StorageTargetNotFoundException::class);

        $registry->get('does_not_exist');
    }

    public function test_resolver_returns_local_provider_for_default_target(): void
    {
        $resolver = $this->app->make(PersistentFileStorageResolver::class);
        $storage = $resolver->resolveDefault();

        $this->assertInstanceOf(LocalPersistentFileStorage::class, $storage);
        $this->assertSame('local', $storage->driver());
        $this->assertSame('local_persistent', $storage->configTarget());
        $this->assertFalse($storage->capabilities()->temporaryUrls);
        $this->assertTrue($storage->capabilities()->streamingReads);
    }

    public function test_resolver_rejects_unsupported_driver(): void
    {
        Config::set('easelogs_storage.targets.future_azure', [
            'driver' => 'azure',
            'root' => '/tmp',
        ]);

        $resolver = $this->app->make(PersistentFileStorageResolver::class);

        $this->expectException(UnsupportedStorageDriverException::class);

        $resolver->resolve('future_azure');
    }

    public function test_config_root_env_override_is_honored(): void
    {
        $customRoot = sys_get_temp_dir().'/easelogs-pfs-config-'.uniqid('', true);
        Config::set('easelogs_storage.targets.local_persistent.root', $customRoot);

        $storage = $this->app->make(PersistentFileStorageResolver::class)->resolveDefault();

        $this->assertInstanceOf(LocalPersistentFileStorage::class, $storage);
        $this->assertSame(rtrim(str_replace('\\', '/', $customRoot), '/'), $storage->rootPath());
    }
}
