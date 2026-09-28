<?php

namespace App\Support\PersistentFileStorage;

/**
 * Provider-neutral description of how to deliver an object to an authorized caller.
 *
 * For stream delivery, the caller uses PersistentFileStorage::readStream().
 * For temporary URL delivery, use temporaryUrl (future cloud providers).
 */
final readonly class FileDeliveryDescriptor
{
    public function __construct(
        public DeliveryKind $kind,
        public ?string $mimeType = null,
        public ?int $sizeBytes = null,
        public ?string $temporaryUrl = null,
        public ?\DateTimeInterface $expiresAt = null,
    ) {}

    public function isStream(): bool
    {
        return $this->kind === DeliveryKind::Stream;
    }

    public function isTemporaryUrl(): bool
    {
        return $this->kind === DeliveryKind::TemporaryUrl;
    }
}
