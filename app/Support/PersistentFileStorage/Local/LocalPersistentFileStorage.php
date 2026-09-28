<?php

namespace App\Support\PersistentFileStorage\Local;

use App\Support\PersistentFileStorage\ConfiguredStorageTarget;
use App\Support\PersistentFileStorage\Contracts\PersistentFileStorage;
use App\Support\PersistentFileStorage\DeliveryKind;
use App\Support\PersistentFileStorage\Exceptions\PersistentFileNotFoundException;
use App\Support\PersistentFileStorage\Exceptions\PersistentFileStorageException;
use App\Support\PersistentFileStorage\Exceptions\PersistentFileStorageRootException;
use App\Support\PersistentFileStorage\Exceptions\UnsupportedDeliveryException;
use App\Support\PersistentFileStorage\FileDeliveryDescriptor;
use App\Support\PersistentFileStorage\ProviderCapabilities;
use App\Support\PersistentFileStorage\StorageKey;
use App\Support\PersistentFileStorage\StoredFile;
use DateTimeInterface;
use FilesystemIterator;
use Throwable;

/**
 * First-class local filesystem persistent storage provider.
 *
 * Supports application-local private roots, administrator-configured absolute
 * paths, and mounted disks/NAS paths presented as local directories.
 * This is a permanent production backend — not a development-only adapter.
 *
 * ## Phase 1 mount guarantees
 *
 * - External / mount roots are **never** auto-created on write (avoids writing
 *   into an empty mount-point directory on the server disk when a NAS is gone).
 * - Application-local roots under `storage/app/` may be created if missing.
 * - `assertRootReady()` supports optional `marker` and `nonempty` checks.
 * - A simple writable-directory check does **not** prove a NAS is correctly
 *   mounted. Full NAS protocol verification remains deferred; operators should
 *   use `mount_check=marker` (or `nonempty`) for mounted volumes.
 */
final class LocalPersistentFileStorage implements PersistentFileStorage
{
    private readonly string $root;

    public function __construct(
        string $root,
        private readonly string $configTargetName = 'local_persistent',
        private readonly string $mountCheck = 'none',
        private readonly string $mountMarker = '.easelogs-storage-ok',
    ) {
        $normalized = rtrim(str_replace('\\', '/', $root), '/');

        if ($normalized === '') {
            throw new PersistentFileStorageRootException('Local persistent storage root must not be empty.');
        }

        $this->root = $normalized;
    }

    public static function fromConfiguredTarget(ConfiguredStorageTarget $target): self
    {
        $root = (string) $target->option('root', '');

        if ($root === '') {
            throw new PersistentFileStorageRootException(
                "Local storage target [{$target->name}] is missing a root path.",
            );
        }

        return new self(
            root: $root,
            configTargetName: $target->name,
            mountCheck: (string) $target->option('mount_check', 'none'),
            mountMarker: (string) $target->option('mount_marker', '.easelogs-storage-ok'),
        );
    }

    public function driver(): string
    {
        return 'local';
    }

    public function configTarget(): string
    {
        return $this->configTargetName;
    }

    /**
     * Absolute root path for diagnostics and tests only — never expose in UI/HTML.
     */
    public function rootPath(): string
    {
        return $this->root;
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            streamingReads: true,
            temporaryUrls: false,
            checksums: true,
            objectMetadata: true,
            serverSideCopy: true,
            rangeRequests: false,
        );
    }

    public function assertRootReady(): void
    {
        if (! is_dir($this->root)) {
            throw new PersistentFileStorageRootException(
                "Local persistent storage root for [{$this->configTargetName}] is missing or not a directory.",
            );
        }

        if (! is_readable($this->root) || ! is_writable($this->root)) {
            throw new PersistentFileStorageRootException(
                "Local persistent storage root for [{$this->configTargetName}] is not readable/writable.",
            );
        }

        // Resolve symlink roots so confinement uses the real path.
        if ($this->resolvedRootPrefix() === null) {
            throw new PersistentFileStorageRootException(
                "Local persistent storage root for [{$this->configTargetName}] could not be resolved.",
            );
        }

        $check = strtolower($this->mountCheck);

        if ($check === 'marker') {
            $markerName = ltrim(str_replace('\\', '/', $this->mountMarker), '/');

            if ($markerName === '' || str_contains($markerName, '/') || str_contains($markerName, '..')) {
                throw new PersistentFileStorageRootException(
                    "Local persistent storage mount marker for [{$this->configTargetName}] is invalid.",
                );
            }

            if (! is_file($this->root.'/'.$markerName)) {
                throw new PersistentFileStorageRootException(
                    "Local persistent storage mount marker for [{$this->configTargetName}] is missing.",
                );
            }
        }

        if ($check === 'nonempty' && $this->directoryIsEffectivelyEmpty($this->root)) {
            throw new PersistentFileStorageRootException(
                "Local persistent storage root for [{$this->configTargetName}] is empty; refusing to treat a vanished mount point as ready.",
            );
        }
    }

    public function put(StorageKey $key, mixed $contents): StoredFile
    {
        return $this->write($key, $contents, null);
    }

    /**
     * @param  resource|string  $contents
     */
    public function putWithOriginalFilename(StorageKey $key, mixed $contents, ?string $originalFilename): StoredFile
    {
        return $this->write($key, $contents, $originalFilename);
    }

    /**
     * @param  resource|string  $contents
     */
    private function write(StorageKey $key, mixed $contents, ?string $originalFilename): StoredFile
    {
        $this->ensureRootAvailableForWrite();
        $absolute = $this->absolutePath($key);
        $this->ensureParentDirectory($absolute);
        $this->assertDestinationSafeToReplace($absolute);

        $temp = $this->temporarySiblingPath($absolute);

        try {
            if (is_resource($contents)) {
                $target = fopen($temp, 'wb');

                if ($target === false) {
                    throw new PersistentFileStorageException("Unable to open temporary file for writing key [{$key}].");
                }

                try {
                    $copied = stream_copy_to_stream($contents, $target);

                    if ($copied === false) {
                        throw new PersistentFileStorageException("Unable to write storage key [{$key}].");
                    }
                } finally {
                    fclose($target);
                }
            } elseif (is_string($contents)) {
                $written = file_put_contents($temp, $contents);

                if ($written === false || $written !== strlen($contents)) {
                    throw new PersistentFileStorageException("Unable to write storage key [{$key}] (short or failed write).");
                }
            } else {
                throw new PersistentFileStorageException('put() requires a string or stream resource.');
            }

            if (! rename($temp, $absolute)) {
                throw new PersistentFileStorageException("Unable to finalize storage key [{$key}].");
            }
        } catch (Throwable $exception) {
            if (is_file($temp)) {
                @unlink($temp);
            }

            throw $exception;
        }

        return $this->inspect($key, $originalFilename);
    }

    public function putFromPath(StorageKey $key, string $absoluteSourcePath, ?string $originalFilename = null): StoredFile
    {
        if (! is_file($absoluteSourcePath) || ! is_readable($absoluteSourcePath)) {
            throw new PersistentFileStorageException(
                "Source intake path for storage key [{$key}] is missing or unreadable.",
            );
        }

        $this->ensureRootAvailableForWrite();
        $absolute = $this->absolutePath($key);
        $this->ensureParentDirectory($absolute);
        $this->assertDestinationSafeToReplace($absolute);

        $temp = $this->temporarySiblingPath($absolute);

        try {
            if (! copy($absoluteSourcePath, $temp)) {
                throw new PersistentFileStorageException("Unable to copy into storage key [{$key}].");
            }

            if (! rename($temp, $absolute)) {
                throw new PersistentFileStorageException("Unable to finalize storage key [{$key}].");
            }
        } catch (Throwable $exception) {
            if (is_file($temp)) {
                @unlink($temp);
            }

            throw $exception;
        }

        return $this->inspect($key, $originalFilename ?? basename($absoluteSourcePath));
    }

    public function readStream(StorageKey $key)
    {
        $absolute = $this->requireExistingFile($key);
        $stream = fopen($absolute, 'rb');

        if ($stream === false) {
            throw new PersistentFileStorageException("Unable to open storage key [{$key}] for reading.");
        }

        return $stream;
    }

    public function get(StorageKey $key): string
    {
        $absolute = $this->requireExistingFile($key);
        $contents = file_get_contents($absolute);

        if ($contents === false) {
            throw new PersistentFileStorageException("Unable to read storage key [{$key}].");
        }

        return $contents;
    }

    public function exists(StorageKey $key): bool
    {
        try {
            $absolute = $this->absolutePath($key);
        } catch (PersistentFileStorageException) {
            return false;
        }

        return is_file($absolute) && ! is_link($absolute);
    }

    public function size(StorageKey $key): int
    {
        $absolute = $this->requireExistingFile($key);
        $size = filesize($absolute);

        if ($size === false) {
            throw new PersistentFileStorageException("Unable to determine size for storage key [{$key}].");
        }

        return $size;
    }

    public function mimeType(StorageKey $key): ?string
    {
        $absolute = $this->requireExistingFile($key);
        $mime = mime_content_type($absolute);

        return $mime === false ? null : $mime;
    }

    public function checksum(StorageKey $key, string $algorithm = 'sha256'): string
    {
        $absolute = $this->requireExistingFile($key);
        $hash = hash_file($algorithm, $absolute);

        if ($hash === false) {
            throw new PersistentFileStorageException("Unable to checksum storage key [{$key}].");
        }

        return $hash;
    }

    public function delete(StorageKey $key): void
    {
        try {
            $absolute = $this->absolutePath($key);
        } catch (PersistentFileStorageException) {
            return;
        }

        if (! is_file($absolute) && ! is_link($absolute)) {
            return;
        }

        // Refuse to operate on a symlink that escapes the root.
        if (is_link($absolute)) {
            $this->assertPathInsideRoot($absolute, allowMissing: false);
        }

        if (! unlink($absolute)) {
            throw new PersistentFileStorageException("Unable to delete storage key [{$key}].");
        }
    }

    public function copy(StorageKey $from, StorageKey $to): StoredFile
    {
        $source = $this->requireExistingFile($from);
        $this->ensureRootAvailableForWrite();
        $destination = $this->absolutePath($to);
        $this->ensureParentDirectory($destination);
        $this->assertDestinationSafeToReplace($destination);

        $temp = $this->temporarySiblingPath($destination);

        try {
            if (! copy($source, $temp)) {
                throw new PersistentFileStorageException("Unable to copy [{$from}] to [{$to}].");
            }

            if (! rename($temp, $destination)) {
                throw new PersistentFileStorageException("Unable to finalize copy to [{$to}].");
            }
        } catch (Throwable $exception) {
            if (is_file($temp)) {
                @unlink($temp);
            }

            throw $exception;
        }

        return $this->inspect($to);
    }

    public function prepareDelivery(StorageKey $key): FileDeliveryDescriptor
    {
        return new FileDeliveryDescriptor(
            kind: DeliveryKind::Stream,
            mimeType: $this->mimeType($key),
            sizeBytes: $this->size($key),
        );
    }

    public function temporaryUrl(StorageKey $key, DateTimeInterface $expiresAt): string
    {
        throw new UnsupportedDeliveryException(
            'Local persistent storage does not issue temporary URLs; use stream delivery.',
        );
    }

    public function inspect(StorageKey $key, ?string $originalFilename = null): StoredFile
    {
        return new StoredFile(
            storageKey: $key,
            originalFilename: $originalFilename,
            mimeType: $this->mimeType($key),
            sizeBytes: $this->size($key),
            sha256: $this->checksum($key),
        );
    }

    /**
     * Application-local roots under storage/app may be created. External/mount
     * roots are never created automatically.
     */
    private function ensureRootAvailableForWrite(): void
    {
        if (is_dir($this->root)) {
            if (! is_writable($this->root)) {
                throw new PersistentFileStorageRootException(
                    "Local persistent storage root for [{$this->configTargetName}] is not writable.",
                );
            }

            return;
        }

        if ($this->isUnderApplicationStorageApp()) {
            if (! mkdir($this->root, 0755, true) && ! is_dir($this->root)) {
                throw new PersistentFileStorageRootException(
                    "Unable to create application-local persistent storage root for [{$this->configTargetName}].",
                );
            }

            return;
        }

        throw new PersistentFileStorageRootException(
            "Local persistent storage root for [{$this->configTargetName}] is missing. External or mount roots are not created automatically.",
        );
    }

    private function isUnderApplicationStorageApp(): bool
    {
        $appStorage = realpath(storage_path('app'));
        $appStorage = $appStorage !== false
            ? rtrim(str_replace('\\', '/', $appStorage), '/')
            : rtrim(str_replace('\\', '/', storage_path('app')), '/');

        return str_starts_with($this->root, $appStorage.'/');
    }

    private function requireExistingFile(StorageKey $key): string
    {
        $absolute = $this->absolutePath($key);

        if (is_link($absolute)) {
            throw new PersistentFileStorageException(
                "Storage key [{$key}] resolves to a symbolic link; refusing to use link targets as durable objects.",
            );
        }

        if (! is_file($absolute)) {
            throw new PersistentFileNotFoundException("Persistent file not found for key [{$key}].");
        }

        if (! is_readable($absolute)) {
            throw new PersistentFileStorageException("Persistent file is unreadable for key [{$key}].");
        }

        return $this->assertPathInsideRoot($absolute, allowMissing: false);
    }

    private function absolutePath(StorageKey $key): string
    {
        $relative = $key->toString();
        $candidate = $this->root.'/'.$relative;
        $rootPrefix = $this->resolvedRootPrefix() ?? $this->root;

        $parent = dirname($candidate);

        if (! is_dir($parent)) {
            $normalized = $this->normalizeAbsoluteLexical($candidate);

            if (! $this->pathIsInsidePrefix($normalized, $rootPrefix)) {
                throw new PersistentFileStorageException('Storage key resolves outside the configured root.');
            }

            return $candidate;
        }

        $parentReal = realpath($parent);

        if ($parentReal === false) {
            throw new PersistentFileStorageException('Unable to resolve storage path parent directory.');
        }

        $parentReal = str_replace('\\', '/', $parentReal);

        if (! $this->pathIsInsidePrefix($parentReal, $rootPrefix)) {
            throw new PersistentFileStorageException('Storage key resolves outside the configured root.');
        }

        $resolved = $parentReal.'/'.basename($candidate);

        if (file_exists($resolved) || is_link($resolved)) {
            return $this->assertPathInsideRoot($resolved, allowMissing: false);
        }

        return $resolved;
    }

    /**
     * @return non-empty-string|null
     */
    private function resolvedRootPrefix(): ?string
    {
        if (! is_dir($this->root)) {
            return null;
        }

        $real = realpath($this->root);

        if ($real === false) {
            return null;
        }

        return rtrim(str_replace('\\', '/', $real), '/');
    }

    private function assertPathInsideRoot(string $path, bool $allowMissing): string
    {
        $rootPrefix = $this->resolvedRootPrefix() ?? $this->root;

        if (is_link($path)) {
            $linkTarget = readlink($path);

            if ($linkTarget === false) {
                throw new PersistentFileStorageException('Unable to inspect symbolic link under storage root.');
            }

            $resolvedTarget = str_starts_with($linkTarget, '/')
                ? $linkTarget
                : dirname($path).'/'.$linkTarget;
            $normalizedTarget = $this->normalizeAbsoluteLexical(str_replace('\\', '/', $resolvedTarget));
            $realTarget = realpath($path);

            if ($realTarget === false || ! $this->pathIsInsidePrefix(str_replace('\\', '/', $realTarget), $rootPrefix)) {
                throw new PersistentFileStorageException('Storage path escapes the configured root via symbolic link.');
            }

            if (! $this->pathIsInsidePrefix($normalizedTarget, $rootPrefix)) {
                throw new PersistentFileStorageException('Storage path escapes the configured root via symbolic link.');
            }

            return str_replace('\\', '/', $realTarget);
        }

        if (! file_exists($path)) {
            if ($allowMissing) {
                $normalized = $this->normalizeAbsoluteLexical(str_replace('\\', '/', $path));

                if (! $this->pathIsInsidePrefix($normalized, $rootPrefix)) {
                    throw new PersistentFileStorageException('Storage key resolves outside the configured root.');
                }

                return $path;
            }

            throw new PersistentFileNotFoundException('Persistent file path is missing under the configured root.');
        }

        $real = realpath($path);

        if ($real === false || ! $this->pathIsInsidePrefix(str_replace('\\', '/', $real), $rootPrefix)) {
            throw new PersistentFileStorageException('Storage path escapes the configured root.');
        }

        return str_replace('\\', '/', $real);
    }

    private function assertDestinationSafeToReplace(string $absolute): void
    {
        if (! file_exists($absolute) && ! is_link($absolute)) {
            return;
        }

        $this->assertPathInsideRoot($absolute, allowMissing: false);

        if (is_link($absolute)) {
            throw new PersistentFileStorageException(
                'Refusing to overwrite a symbolic link inside the storage root.',
            );
        }
    }

    private function pathIsInsidePrefix(string $path, string $prefix): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $prefix = rtrim(str_replace('\\', '/', $prefix), '/');

        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }

    private function normalizeAbsoluteLexical(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = [];
        $absolute = str_starts_with($path, '/');

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $segment;
        }

        return ($absolute ? '/' : '').implode('/', $parts);
    }

    private function temporarySiblingPath(string $absoluteFilePath): string
    {
        return $absoluteFilePath.'.'.bin2hex(random_bytes(8)).'.tmp';
    }

    private function ensureParentDirectory(string $absoluteFilePath): void
    {
        $directory = dirname($absoluteFilePath);

        if (is_dir($directory)) {
            if (is_link($directory)) {
                $this->assertPathInsideRoot($directory, allowMissing: false);
            }

            return;
        }

        if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new PersistentFileStorageException('Unable to create storage directory for object key.');
        }

        $real = realpath($directory);
        $rootPrefix = $this->resolvedRootPrefix() ?? $this->root;

        if ($real === false || ! $this->pathIsInsidePrefix(str_replace('\\', '/', $real), $rootPrefix)) {
            throw new PersistentFileStorageException('Storage directory resolves outside the configured root.');
        }
    }

    private function directoryIsEffectivelyEmpty(string $directory): bool
    {
        $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            $name = $item->getFilename();

            if ($name === '.gitignore') {
                continue;
            }

            return false;
        }

        return true;
    }
}
