<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class DemoMode
{
    public const UPLOAD_BEHAVIOR_ENABLED = 'enabled';

    public const UPLOAD_BEHAVIOR_DISABLED = 'disabled';

    public const UPLOAD_BEHAVIOR_DISCARD = 'discard';

    public const PUBLIC_BANNER_MESSAGE = 'Public demo mode: data may reset periodically. Uploads are discarded and some actions are disabled.';

    public const MESSAGE_UPLOADS_DISABLED = 'Uploads are disabled in the public demo environment.';

    public const MESSAGE_UPLOAD_DISCARDED = 'Demo upload accepted. The uploaded file was discarded and no file was stored.';

    public const MESSAGE_UPLOAD_DISCARD_NOTICE = 'Public demo mode: uploads can be tested, but files are discarded and not stored.';

    public const MESSAGE_IMPORTS = 'CSV imports are disabled in the public demo environment.';

    public const MESSAGE_ACCOUNT_CHANGES = 'Account changes are disabled in the public demo environment.';

    public const MESSAGE_DELETES = 'Deletes are disabled in the public demo environment.';

    public const MESSAGE_REGISTRATION = 'New account registration is disabled in the public demo environment.';

    public const MESSAGE_PASSWORD_RESET = 'Password reset is disabled in the public demo environment.';

    public const MESSAGE_EMAIL_SENDING = 'Outbound email is disabled in the public demo environment.';

    public const MESSAGE_EXTERNAL_WEBHOOKS = 'External webhooks are disabled in the public demo environment.';

    public const ATTRIBUTE_UPLOAD_DISCARDED = 'easelogs_demo_upload_discarded';

    /**
     * @var list<string>
     */
    private const UPLOAD_BEHAVIORS = [
        self::UPLOAD_BEHAVIOR_ENABLED,
        self::UPLOAD_BEHAVIOR_DISABLED,
        self::UPLOAD_BEHAVIOR_DISCARD,
    ];

    /**
     * @var array<string, string>
     */
    private const ALLOW_CONFIG_KEYS = [
        'imports' => 'allow_imports',
        'account_changes' => 'allow_account_changes',
        'deletes' => 'allow_deletes',
        'registration' => 'allow_registration',
        'password_reset' => 'allow_password_reset',
        'email_sending' => 'allow_email_sending',
        'external_webhooks' => 'allow_external_webhooks',
    ];

    public static function enabled(): bool
    {
        return (bool) config('easelogs.demo_mode');
    }

    public static function showPublicNotice(): bool
    {
        return self::enabled() && (bool) config('easelogs.demo.show_public_notice', true);
    }

    public static function uploadBehavior(): string
    {
        if (! self::enabled()) {
            return self::UPLOAD_BEHAVIOR_ENABLED;
        }

        $behavior = strtolower((string) config('easelogs.demo.upload_behavior', self::UPLOAD_BEHAVIOR_ENABLED));

        return in_array($behavior, self::UPLOAD_BEHAVIORS, true)
            ? $behavior
            : self::UPLOAD_BEHAVIOR_ENABLED;
    }

    public static function uploadWasDiscarded(Request $request): bool
    {
        return (bool) $request->attributes->get(self::ATTRIBUTE_UPLOAD_DISCARDED);
    }

    public static function allows(string $capability): bool
    {
        if (! self::enabled()) {
            return true;
        }

        $configKey = self::ALLOW_CONFIG_KEYS[$capability] ?? null;

        if ($configKey === null) {
            return true;
        }

        return (bool) config('easelogs.demo.'.$configKey, false);
    }

    public static function blocks(string $capability): bool
    {
        return self::enabled() && ! self::allows($capability);
    }

    public static function blocksAccountChangesFor(?User $user): bool
    {
        if (! self::enabled()) {
            return false;
        }

        if ($user !== null && DemoUser::isDemoUser($user)) {
            return self::blocks('account_changes');
        }

        return self::blocks('account_changes');
    }

    public static function ensureAccountChangesAllowed(?User $user): void
    {
        if (! self::blocksAccountChangesFor($user)) {
            return;
        }

        throw new HttpException(403, self::MESSAGE_ACCOUNT_CHANGES);
    }

    public static function blocksUploadControls(): bool
    {
        return self::enabled() && self::uploadBehavior() === self::UPLOAD_BEHAVIOR_DISABLED;
    }

    public static function discardsUploads(): bool
    {
        return self::enabled() && self::uploadBehavior() === self::UPLOAD_BEHAVIOR_DISCARD;
    }

    /**
     * @return array<string, mixed>
     */
    public static function viewData(): array
    {
        $user = auth()->user();

        return [
            'enabled' => self::enabled(),
            'show_public_notice' => self::showPublicNotice(),
            'public_banner_message' => self::PUBLIC_BANNER_MESSAGE,
            'upload_behavior' => self::uploadBehavior(),
            'blocks_upload_controls' => self::blocksUploadControls(),
            'discards_uploads' => self::discardsUploads(),
            'allows_imports' => self::allows('imports'),
            'allows_account_changes' => self::allows('account_changes'),
            'allows_deletes' => self::allows('deletes'),
            'allows_registration' => self::allows('registration'),
            'blocks_imports' => self::blocks('imports'),
            'blocks_account_changes' => self::blocksAccountChangesFor($user),
            'is_demo_user' => DemoUser::isDemoUser($user),
            'demo_login' => DemoUser::loginViewData(),
            'blocks_deletes' => self::blocks('deletes'),
            'blocks_registration' => self::blocks('registration'),
            'message_uploads_disabled' => self::MESSAGE_UPLOADS_DISABLED,
            'message_upload_discard_notice' => self::MESSAGE_UPLOAD_DISCARD_NOTICE,
            'message_imports' => self::MESSAGE_IMPORTS,
            'message_account_changes' => self::MESSAGE_ACCOUNT_CHANGES,
            'message_deletes' => self::MESSAGE_DELETES,
            'message_registration' => self::MESSAGE_REGISTRATION,
        ];
    }

    public static function ensureAllowed(string $capability): void
    {
        if (self::allows($capability)) {
            return;
        }

        throw new HttpException(403, self::messageFor($capability));
    }

    public static function ensureDeletesAllowed(): void
    {
        self::ensureAllowed('deletes');
    }

    /**
     * Last-resort guard when photo bytes would be persisted outside HTTP middleware.
     */
    public static function ensurePhotoStorageAllowed(): void
    {
        if (! self::enabled()) {
            return;
        }

        if (self::uploadBehavior() === self::UPLOAD_BEHAVIOR_ENABLED) {
            return;
        }

        throw new HttpException(
            403,
            self::uploadBehavior() === self::UPLOAD_BEHAVIOR_DISABLED
                ? self::MESSAGE_UPLOADS_DISABLED
                : self::MESSAGE_UPLOAD_DISCARDED,
        );
    }

    /**
     * Enforce demo upload policy before controllers and form requests run.
     */
    public static function processUploadRequest(Request $request): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        if (! self::enabled()) {
            return;
        }

        $behavior = self::uploadBehavior();

        if ($behavior === self::UPLOAD_BEHAVIOR_ENABLED) {
            return;
        }

        if ($behavior === self::UPLOAD_BEHAVIOR_DISABLED) {
            throw new HttpException(403, self::MESSAGE_UPLOADS_DISABLED);
        }

        $request->attributes->set(self::ATTRIBUTE_UPLOAD_DISCARDED, true);
        $request->files->remove('photo');
    }

    public static function messageFor(string $capability): string
    {
        return match ($capability) {
            'imports' => self::MESSAGE_IMPORTS,
            'account_changes' => self::MESSAGE_ACCOUNT_CHANGES,
            'deletes' => self::MESSAGE_DELETES,
            'registration' => self::MESSAGE_REGISTRATION,
            'password_reset' => self::MESSAGE_PASSWORD_RESET,
            'email_sending' => self::MESSAGE_EMAIL_SENDING,
            'external_webhooks' => self::MESSAGE_EXTERNAL_WEBHOOKS,
            default => 'This action is disabled in the public demo environment.',
        };
    }
}
