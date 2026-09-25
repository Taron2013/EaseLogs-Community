<?php

namespace App\Support;

/**
 * Fail-closed rejection from ArtworkImageQualifier.
 *
 * Extends InvalidArgumentException so existing import/reconcile catch sites
 * that already handle InvalidArgumentException continue to work.
 */
class ArtworkImageRejectedException extends \InvalidArgumentException
{
    public const REASON_UNREADABLE = 'unreadable';

    public const REASON_OVERSIZE = 'oversize';

    public const REASON_UNSUPPORTED_TYPE = 'unsupported_type';

    public const REASON_UNDECODABLE = 'undecodable';

    public const REASON_INVALID_DIMENSIONS = 'invalid_dimensions';

    public function __construct(
        public readonly string $reason,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $this->defaultMessage($reason), $code, $previous);
    }

    private function defaultMessage(string $reason): string
    {
        return match ($reason) {
            self::REASON_UNREADABLE => 'Image file is missing or unreadable.',
            self::REASON_OVERSIZE => 'Image exceeds the configured maximum upload size.',
            self::REASON_UNSUPPORTED_TYPE => 'Image type is not supported.',
            self::REASON_UNDECODABLE => 'Image could not be decoded as a supported type.',
            self::REASON_INVALID_DIMENSIONS => 'Image dimensions are invalid.',
            default => 'Image was rejected.',
        };
    }
}
