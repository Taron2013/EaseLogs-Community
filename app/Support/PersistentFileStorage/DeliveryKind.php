<?php

namespace App\Support\PersistentFileStorage;

/**
 * How a provider prefers to deliver bytes to an authorized caller.
 *
 * Callers branch on this capability result — not on provider driver names.
 */
enum DeliveryKind: string
{
    case Stream = 'stream';
    case TemporaryUrl = 'temporary_url';
}
