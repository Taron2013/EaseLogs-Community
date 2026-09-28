<?php

namespace App\Support\PersistentFileStorage\Contracts;

use App\Support\PersistentFileStorage\FileDeliveryDescriptor;
use App\Support\PersistentFileStorage\ProviderCapabilities;
use App\Support\PersistentFileStorage\StorageKey;
use App\Support\PersistentFileStorage\StoredFile;
use DateTimeInterface;

/**
 * Provider-neutral persistent file storage contract.
 *
 * Implementations must remain domain-agnostic (no Artwork / domain business logic).
 * Callers must not receive Laravel disk names, AWS/Azure SDK objects, permanent
 * cloud URLs, or rely on absolute filesystem paths from this API.
 *
 * ## Semantics
 *
 * - **Logical keys** are immutable value objects ({@see StorageKey}).
 * - **put / putFromPath / copy** overwrite an existing object at the same key.
 *   Callers that need create-only semantics must check exists() first.
 * - **delete** is idempotent: missing keys are a no-op.
 * - **Missing objects** on read/size/mime/checksum/delivery throw
 *   PersistentFileNotFoundException (or a subclass).
 * - **checksum()** returns a lowercase hex digest; default algorithm is sha256.
 * - **mimeType()** may return null when the provider cannot determine a type.
 * - **size()** is the stored byte length.
 * - **get()** loads the entire object into memory — use readStream() for large files.
 * - **readStream()** returns an open readable stream. The **caller owns** the
 *   stream and must fclose() it. The provider does not close caller-supplied
 *   input streams passed to put().
 * - **prepareDelivery()** describes how to deliver; for stream delivery the
 *   caller still uses readStream(). Temporary URLs are only valid when
 *   capabilities()->temporaryUrls is true.
 * - Prefer capabilities() over driver() when adapting behavior.
 */
interface PersistentFileStorage
{
    /**
     * Stable driver identifier (e.g. "local"). Prefer capabilities() over branching on this.
     */
    public function driver(): string;

    /**
     * Configured target name from the installation registry (e.g. "local_persistent").
     */
    public function configTarget(): string;

    public function capabilities(): ProviderCapabilities;

    /**
     * Verify the storage root is present and usable before relying on writes.
     *
     * Does not create missing roots. Fails closed for configured mount checks.
     */
    public function assertRootReady(): void;

    /**
     * Store bytes at the logical key (string contents or an open readable stream).
     *
     * Overwrites any existing object at the same key.
     * Does not close a caller-supplied stream resource.
     *
     * @param  resource|string  $contents
     */
    public function put(StorageKey $key, mixed $contents): StoredFile;

    /**
     * Copy bytes from a local absolute filesystem path into persistent storage.
     *
     * Overwrites any existing object at the same key.
     * The source path is a transient intake location — not a durable storage key.
     */
    public function putFromPath(StorageKey $key, string $absoluteSourcePath, ?string $originalFilename = null): StoredFile;

    /**
     * Open a readable stream for the object.
     *
     * Caller must fclose() the returned stream.
     *
     * @return resource
     */
    public function readStream(StorageKey $key);

    /**
     * Read the entire object into a string. Prefer readStream() for large objects.
     */
    public function get(StorageKey $key): string;

    public function exists(StorageKey $key): bool;

    public function size(StorageKey $key): int;

    public function mimeType(StorageKey $key): ?string;

    /**
     * Hex-encoded checksum. Default algorithm is sha256.
     */
    public function checksum(StorageKey $key, string $algorithm = 'sha256'): string;

    /**
     * Remove the object. Idempotent when the key is already absent.
     */
    public function delete(StorageKey $key): void;

    /**
     * Copy within the same provider. Overwrites the destination key if present.
     */
    public function copy(StorageKey $from, StorageKey $to): StoredFile;

    /**
     * Describe how an authorized delivery layer should serve this object.
     * Descriptors must not embed absolute filesystem paths.
     */
    public function prepareDelivery(StorageKey $key): FileDeliveryDescriptor;

    /**
     * Short-lived URL when capabilities()->temporaryUrls is true; otherwise throws.
     */
    public function temporaryUrl(StorageKey $key, DateTimeInterface $expiresAt): string;
}
