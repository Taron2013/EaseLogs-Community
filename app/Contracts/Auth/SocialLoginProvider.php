<?php

namespace App\Contracts\Auth;

/**
 * Extension point for future OAuth / social login providers.
 *
 * Planned providers: Google, Microsoft, Facebook (Meta), GitHub.
 * See docs/AUTH_EXTENSIONS.md and config('easelogs.auth.social_providers').
 */
interface SocialLoginProvider
{
    /**
     * Stable provider key (for example: google, microsoft, facebook, github).
     */
    public function providerKey(): string;

    /**
     * URL that starts the OAuth authorization redirect.
     */
    public function redirectUrl(): string;
}
