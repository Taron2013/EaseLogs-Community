<?php

namespace App\Support\Storage;

use RuntimeException;

/**
 * Fail-closed when the installation storage tenant UUID is missing or invalid
 * while private artwork-photo bindings already exist.
 */
final class InstallationStorageIdentityMissingException extends RuntimeException
{
}
