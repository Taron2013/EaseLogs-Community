<?php

namespace App\Support\PersistentFileStorage;

/**
 * Provider-neutral logical categories of durable EaseLogs-managed files.
 *
 * Domains participate in logical-key construction and higher-level policy.
 * Provider implementations must not contain domain-specific business logic.
 *
 * @see docs/PERSISTENT_FILE_STORAGE.md §6
 */
enum StorageDomain: string
{
    case ArtworkPhotos = 'artwork_photos';
    case Certificates = 'certificates';
    case ProvenanceDocuments = 'provenance_documents';
    case Attachments = 'attachments';
    case Exports = 'exports';
}
