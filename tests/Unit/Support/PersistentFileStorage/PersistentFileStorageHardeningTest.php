<?php

namespace Tests\Unit\Support\PersistentFileStorage;

use App\Support\PersistentFileStorage\Exceptions\InvalidStorageKeyException;
use App\Support\PersistentFileStorage\Exceptions\PersistentFileNotFoundException;
use App\Support\PersistentFileStorage\Exceptions\PersistentFileStorageException;
use App\Support\PersistentFileStorage\Exceptions\PersistentFileStorageRootException;
use App\Support\PersistentFileStorage\Local\LocalPersistentFileStorage;
use App\Support\PersistentFileStorage\PersistentFileStorageResolver;
use App\Support\PersistentFileStorage\StorageDomain;
use App\Support\PersistentFileStorage\StorageKey;
use App\Support\PersistentFileStorage\StorageKeyFactory;
use App\Support\PersistentFileStorage\StorageTargetRegistry;
use App\Support\PersistentFileStorage\TenantScope;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersistentFileStorageHardeningTest extends TestCase
{
    private string $root;

    private LocalPersistentFileStorage $storage;

    private StorageKeyFactory $keys;

    private TenantScope $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/easelogs-pfs-hard-'.Str::uuid()->toString();
        mkdir($this->root, 0755, true);

        $this->storage = new LocalPersistentFileStorage($this->root, 'local_persistent');
        $this->keys = new StorageKeyFactory;
        $this->tenant = TenantScope::fromUuid((string) Str::uuid());
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            @chmod($this->root, 0755);
            $this->deleteTree($this->root);
        }

        parent::tearDown();
    }

    public function test_storage_key_rejects_absolute_unix_and_windows_paths(): void
    {
        foreach (['/etc/passwd', 'C:/Windows/System32', 'C:\\Windows\\System32'] as $invalid) {
            try {
                StorageKey::fromString($invalid);
                $this->fail('Expected rejection for '.$invalid);
            } catch (InvalidStorageKeyException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_storage_key_normalizes_windows_separators_as_segments(): void
    {
        $key = StorageKey::fromString('studios\\abc\\file.txt');

        $this->assertSame('studios/abc/file.txt', $key->toString());
    }

    public function test_storage_key_rejects_null_bytes_control_chars_and_unicode(): void
    {
        foreach (["foo\0bar", "foo\nbar", 'studios/café/x', "foo\x7Fbar"] as $invalid) {
            try {
                StorageKey::fromString($invalid);
                $this->fail('Expected rejection for control/unicode key');
            } catch (InvalidStorageKeyException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_storage_key_rejects_empty_dot_and_repeated_separator_collapse_to_empty(): void
    {
        $this->expectException(InvalidStorageKeyException::class);

        StorageKey::fromString('///');
    }

    public function test_storage_key_strips_dot_segments_when_other_segments_remain(): void
    {
        $key = StorageKey::fromString('studios/./abc/./file.txt');

        $this->assertSame('studios/abc/file.txt', $key->toString());
    }

    public function test_put_overwrites_existing_logical_key(): void
    {
        $key = $this->sampleKey('txt');
        $this->storage->put($key, 'first');
        $this->storage->put($key, 'second');

        $this->assertSame('second', $this->storage->get($key));
    }

    public function test_delete_is_idempotent_for_missing_keys(): void
    {
        $key = $this->sampleKey('txt');

        $this->storage->delete($key);
        $this->storage->delete($key);

        $this->assertFalse($this->storage->exists($key));
    }

    public function test_read_stream_caller_owns_and_closes_stream(): void
    {
        $key = $this->sampleKey('txt');
        $this->storage->put($key, 'stream-data');

        $stream = $this->storage->readStream($key);
        $this->assertIsResource($stream);
        $this->assertSame('stream-data', stream_get_contents($stream));
        fclose($stream);
    }

    public function test_put_from_stream_does_not_close_caller_input_stream(): void
    {
        $key = $this->sampleKey('txt');
        $input = fopen('php://memory', 'r+b');
        $this->assertNotFalse($input);
        fwrite($input, 'from-memory');
        rewind($input);

        $this->storage->put($key, $input);

        $this->assertIsResource($input);
        $this->assertSame('from-memory', $this->storage->get($key));
        fclose($input);
    }

    public function test_missing_external_root_is_not_auto_created(): void
    {
        $missing = sys_get_temp_dir().'/easelogs-pfs-missing-'.Str::uuid()->toString();
        $storage = new LocalPersistentFileStorage($missing, 'local_persistent');

        try {
            $storage->put($this->sampleKey('txt'), 'nope');
            $this->fail('Expected missing external root to fail.');
        } catch (PersistentFileStorageRootException $exception) {
            $this->assertFalse(is_dir($missing));
            $this->assertStringNotContainsString($missing, $exception->getMessage());
        }
    }

    public function test_unwritable_root_fails_visibly(): void
    {
        chmod($this->root, 0555);

        if (is_writable($this->root)) {
            $this->markTestSkipped('Unable to create an unwritable root in this environment.');
        }

        $this->expectException(PersistentFileStorageRootException::class);

        $this->storage->put($this->sampleKey('txt'), 'nope');
    }

    public function test_symlink_escape_is_rejected_on_read_and_overwrite(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('symlink() unavailable.');
        }

        $outside = sys_get_temp_dir().'/easelogs-pfs-outside-'.Str::uuid()->toString().'.txt';
        file_put_contents($outside, 'secret-outside');

        $key = $this->sampleKey('txt');
        $destination = $this->root.'/'.$key->toString();
        mkdir(dirname($destination), 0755, true);

        $linked = @symlink($outside, $destination);

        if ($linked !== true) {
            @unlink($outside);
            $this->markTestSkipped('Unable to create symlink in this environment.');
        }

        try {
            try {
                $this->storage->get($key);
                $this->fail('Expected symlink read to fail.');
            } catch (PersistentFileStorageException) {
                $this->assertTrue(true);
            }

            try {
                $this->storage->put($key, 'overwrite');
                $this->fail('Expected symlink overwrite to fail.');
            } catch (PersistentFileStorageException) {
                $this->assertTrue(true);
            }

            $this->assertSame('secret-outside', file_get_contents($outside));
        } finally {
            @unlink($destination);
            @unlink($outside);
        }
    }

    public function test_delivery_descriptor_and_stored_file_omit_absolute_paths(): void
    {
        $key = $this->sampleKey('txt');
        $stored = $this->storage->put($key, 'meta');
        $delivery = $this->storage->prepareDelivery($key);

        $this->assertStringNotContainsString($this->root, $stored->storageKey->toString());
        $this->assertNull($delivery->temporaryUrl);
        $this->assertSame($key->toString(), (string) $stored->storageKey);
    }

    public function test_root_exception_messages_omit_absolute_paths(): void
    {
        $missing = sys_get_temp_dir().'/easelogs-pfs-absent-'.Str::uuid()->toString();
        $storage = new LocalPersistentFileStorage($missing, 'local_persistent');

        try {
            $storage->assertRootReady();
            $this->fail('Expected assertRootReady to fail.');
        } catch (PersistentFileStorageRootException $exception) {
            $this->assertStringNotContainsString($missing, $exception->getMessage());
            $this->assertStringContainsString('local_persistent', $exception->getMessage());
        }
    }

    public function test_resolver_isolates_configured_targets(): void
    {
        $rootA = $this->root.'/target-a';
        $rootB = $this->root.'/target-b';
        mkdir($rootA, 0755, true);
        mkdir($rootB, 0755, true);

        Config::set('easelogs_storage.targets.local_a', [
            'driver' => 'local',
            'root' => $rootA,
            'mount_check' => 'none',
        ]);
        Config::set('easelogs_storage.targets.local_b', [
            'driver' => 'local',
            'root' => $rootB,
            'mount_check' => 'none',
        ]);

        $resolver = $this->app->make(PersistentFileStorageResolver::class);
        $storageA = $resolver->resolve('local_a');
        $storageB = $resolver->resolve('local_b');

        $this->assertInstanceOf(LocalPersistentFileStorage::class, $storageA);
        $this->assertInstanceOf(LocalPersistentFileStorage::class, $storageB);
        $this->assertSame($rootA, $storageA->rootPath());
        $this->assertSame($rootB, $storageB->rootPath());

        $key = $this->sampleKey('txt');
        $storageA->put($key, 'only-a');

        $this->assertTrue($storageA->exists($key));
        $this->assertFalse($storageB->exists($key));
    }

    public function test_config_registry_uses_laravel_config_not_runtime_env_helper_outside_files(): void
    {
        $custom = $this->root.'/from-config';
        mkdir($custom, 0755, true);
        Config::set('easelogs_storage.default_target', 'local_persistent');
        Config::set('easelogs_storage.targets.local_persistent', [
            'driver' => 'local',
            'root' => $custom,
            'mount_check' => 'none',
            'mount_marker' => '.easelogs-storage-ok',
        ]);

        $registry = $this->app->make(StorageTargetRegistry::class);
        $target = $registry->default();

        $this->assertSame($custom, $target->option('root'));

        $storage = $this->app->make(PersistentFileStorageResolver::class)->resolveDefault();
        $this->assertInstanceOf(LocalPersistentFileStorage::class, $storage);
        $this->assertSame($custom, $storage->rootPath());
    }

    public function test_capabilities_report_no_temporary_urls_for_local(): void
    {
        $capabilities = $this->storage->capabilities();

        $this->assertFalse($capabilities->temporaryUrls);
        $this->assertTrue($capabilities->streamingReads);
        $this->assertFalse($this->storage->prepareDelivery($this->putSample())->isTemporaryUrl());
    }

    public function test_missing_file_operations_throw_not_found(): void
    {
        $key = $this->sampleKey('missing');

        $this->expectException(PersistentFileNotFoundException::class);
        $this->storage->size($key);
    }

    private function putSample(): StorageKey
    {
        $key = $this->sampleKey('txt');
        $this->storage->put($key, 'x');

        return $key;
    }

    private function sampleKey(string $extension): StorageKey
    {
        return $this->keys->makeOriginal(
            $this->tenant,
            StorageDomain::Attachments,
            (string) Str::uuid(),
            (string) Str::uuid(),
            $extension,
        );
    }

    private function deleteTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path.'/'.$item;

            if (is_dir($full) && ! is_link($full)) {
                $this->deleteTree($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }
}
