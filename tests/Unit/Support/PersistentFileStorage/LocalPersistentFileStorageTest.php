<?php

namespace Tests\Unit\Support\PersistentFileStorage;

use App\Support\PersistentFileStorage\DeliveryKind;
use App\Support\PersistentFileStorage\Exceptions\InvalidStorageKeyException;
use App\Support\PersistentFileStorage\Exceptions\PersistentFileNotFoundException;
use App\Support\PersistentFileStorage\Exceptions\PersistentFileStorageRootException;
use App\Support\PersistentFileStorage\Exceptions\UnsupportedDeliveryException;
use App\Support\PersistentFileStorage\Local\LocalPersistentFileStorage;
use App\Support\PersistentFileStorage\StorageDomain;
use App\Support\PersistentFileStorage\StorageKey;
use App\Support\PersistentFileStorage\StorageKeyFactory;
use App\Support\PersistentFileStorage\TenantScope;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocalPersistentFileStorageTest extends TestCase
{
    private string $root;

    private LocalPersistentFileStorage $storage;

    private StorageKeyFactory $keys;

    private TenantScope $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/easelogs-pfs-'.Str::uuid()->toString();
        mkdir($this->root, 0755, true);

        $this->storage = new LocalPersistentFileStorage($this->root, 'local_persistent');
        $this->keys = new StorageKeyFactory;
        $this->tenant = TenantScope::fromUuid((string) Str::uuid());
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->root);

        parent::tearDown();
    }

    public function test_put_get_exists_size_checksum_and_delete(): void
    {
        $key = $this->sampleKey('txt');
        $stored = $this->storage->put($key, 'hello world');

        $this->assertTrue($this->storage->exists($key));
        $this->assertSame('hello world', $this->storage->get($key));
        $this->assertSame(11, $this->storage->size($key));
        $this->assertSame(hash('sha256', 'hello world'), $this->storage->checksum($key));
        $this->assertSame(hash('sha256', 'hello world'), $stored->sha256);
        $this->assertTrue($stored->storageKey->equals($key));

        $this->storage->delete($key);
        $this->assertFalse($this->storage->exists($key));
    }

    public function test_put_from_path_preserves_original_filename_metadata(): void
    {
        $source = $this->root.'/upload-source.bin';
        file_put_contents($source, 'from-path');

        $key = $this->sampleKey('bin');
        $stored = $this->storage->putFromPath($key, $source, 'Artist Scan.BIN');

        $this->assertSame('from-path', $this->storage->get($key));
        $this->assertSame('Artist Scan.BIN', $stored->originalFilename);
        $this->assertSame(9, $stored->sizeBytes);
    }

    public function test_read_stream_and_copy_within_provider(): void
    {
        $from = $this->sampleKey('dat');
        $to = $this->sampleKey('dat');

        $this->storage->put($from, 'stream-me');

        $stream = $this->storage->readStream($from);
        $this->assertSame('stream-me', stream_get_contents($stream));
        fclose($stream);

        $copied = $this->storage->copy($from, $to);
        $this->assertTrue($this->storage->exists($to));
        $this->assertSame('stream-me', $this->storage->get($to));
        $this->assertSame(hash('sha256', 'stream-me'), $copied->sha256);
    }

    public function test_prepare_delivery_is_stream_and_temporary_url_unsupported(): void
    {
        $key = $this->sampleKey('txt');
        $this->storage->put($key, 'deliver');

        $delivery = $this->storage->prepareDelivery($key);

        $this->assertSame(DeliveryKind::Stream, $delivery->kind);
        $this->assertTrue($delivery->isStream());
        $this->assertSame(7, $delivery->sizeBytes);

        $this->expectException(UnsupportedDeliveryException::class);
        $this->storage->temporaryUrl($key, new DateTimeImmutable('+5 minutes'));
    }

    public function test_missing_file_throws(): void
    {
        $this->expectException(PersistentFileNotFoundException::class);

        $this->storage->get($this->sampleKey('missing'));
    }

    public function test_assert_root_ready_requires_directory(): void
    {
        $missing = $this->root.'/does-not-exist-yet';
        $storage = new LocalPersistentFileStorage($missing);

        $this->expectException(PersistentFileStorageRootException::class);
        $storage->assertRootReady();
    }

    public function test_assert_root_ready_marker_check(): void
    {
        $storage = new LocalPersistentFileStorage(
            root: $this->root,
            mountCheck: 'marker',
            mountMarker: '.easelogs-storage-ok',
        );

        try {
            $storage->assertRootReady();
            $this->fail('Expected marker check to fail.');
        } catch (PersistentFileStorageRootException) {
            // expected
        }

        file_put_contents($this->root.'/.easelogs-storage-ok', 'ok');
        $storage->assertRootReady();
        $this->assertTrue(true);
    }

    public function test_assert_root_ready_nonempty_check(): void
    {
        $emptyRoot = $this->root.'/empty-mount';
        mkdir($emptyRoot, 0755, true);

        $storage = new LocalPersistentFileStorage(
            root: $emptyRoot,
            mountCheck: 'nonempty',
        );

        $this->expectException(PersistentFileStorageRootException::class);
        $storage->assertRootReady();
    }

    public function test_rejects_traversal_even_if_key_construction_bypassed(): void
    {
        $this->expectException(InvalidStorageKeyException::class);

        StorageKey::fromString('studios/x/../secret');
    }

    public function test_capabilities_do_not_require_provider_conditionals_for_local(): void
    {
        $capabilities = $this->storage->capabilities()->toArray();

        $this->assertTrue($capabilities['streaming_reads']);
        $this->assertFalse($capabilities['temporary_urls']);
        $this->assertTrue($capabilities['checksums']);
        $this->assertTrue($capabilities['server_side_copy']);
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

            if (is_dir($full)) {
                $this->deleteTree($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }
}
