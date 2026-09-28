<?php

namespace Tests\Unit\Support\PersistentFileStorage;

use App\Support\PersistentFileStorage\Exceptions\InvalidStorageKeyException;
use App\Support\PersistentFileStorage\StorageDomain;
use App\Support\PersistentFileStorage\StorageKey;
use App\Support\PersistentFileStorage\StorageKeyFactory;
use App\Support\PersistentFileStorage\TenantScope;
use InvalidArgumentException;
use Tests\TestCase;

class StorageKeyFactoryTest extends TestCase
{
    public function test_builds_stable_logical_key_with_domain_segment(): void
    {
        $tenant = TenantScope::fromUuid('11111111-1111-4111-8111-111111111111');
        $factory = new StorageKeyFactory;

        $key = $factory->makeOriginal(
            $tenant,
            StorageDomain::ArtworkPhotos,
            '22222222-2222-4222-8222-222222222222',
            '33333333-3333-4333-8333-333333333333',
            'JPG',
        );

        $this->assertSame(
            'studios/11111111-1111-4111-8111-111111111111/artwork_photos/22222222-2222-4222-8222-222222222222/33333333-3333-4333-8333-333333333333/original.jpg',
            $key->toString(),
        );
    }

    public function test_storage_domains_are_provider_neutral_string_values(): void
    {
        $this->assertSame('artwork_photos', StorageDomain::ArtworkPhotos->value);
        $this->assertSame('certificates', StorageDomain::Certificates->value);
        $this->assertSame('provenance_documents', StorageDomain::ProvenanceDocuments->value);
        $this->assertSame('attachments', StorageDomain::Attachments->value);
        $this->assertSame('exports', StorageDomain::Exports->value);
    }

    public function test_rejects_path_traversal_and_absolute_keys(): void
    {
        $this->expectException(InvalidStorageKeyException::class);

        StorageKey::fromString('../etc/passwd');
    }

    public function test_rejects_url_keys(): void
    {
        $this->expectException(InvalidStorageKeyException::class);

        StorageKey::fromString('https://example.com/object');
    }

    public function test_tenant_scope_rejects_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantScope::fromUuid('not-a-uuid');
    }

    public function test_factory_rejects_invalid_record_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new StorageKeyFactory)->make(
            TenantScope::fromUuid('11111111-1111-4111-8111-111111111111'),
            StorageDomain::Exports,
            'bad',
            '33333333-3333-4333-8333-333333333333',
            'archive.zip',
        );
    }
}
