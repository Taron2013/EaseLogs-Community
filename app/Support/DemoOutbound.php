<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Central guard for outbound side effects (email, webhooks, payments).
 */
final class DemoOutbound
{
    public static function ensureEmailAllowed(): void
    {
        DemoMode::ensureAllowed('email_sending');
    }

    public static function ensureWebhookAllowed(): void
    {
        DemoMode::ensureAllowed('external_webhooks');
    }

    /**
     * Call before dispatching payment or license activation requests.
     */
    public static function ensurePaymentActionAllowed(): void
    {
        if (! DemoMode::enabled()) {
            return;
        }

        throw new HttpException(403, 'Payment and license actions are disabled in the public demo environment.');
    }

    public static function ensurePasswordResetAllowed(): void
    {
        DemoMode::ensureAllowed('password_reset');
    }

    /**
     * Call before Pro/Enterprise file, document, or attachment upload/replace routes.
     */
    public static function ensureProFileWriteAllowed(): void
    {
        DemoMode::ensurePhotoStorageAllowed();
    }

    /**
     * Call before Pro/Enterprise file, document, or attachment delete routes.
     */
    public static function ensureProFileDeleteAllowed(): void
    {
        DemoMode::ensureDeletesAllowed();
    }
}
