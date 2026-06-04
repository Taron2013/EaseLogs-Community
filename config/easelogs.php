<?php

use App\Support\UrlPrefix;

return [

    /*
    |--------------------------------------------------------------------------
    | URL path prefix (subdirectory deployment)
    |--------------------------------------------------------------------------
    |
    | When set (e.g. "community"), web routes are registered under /community.
    | Set APP_URL to the full public URL including this path segment.
    |
    */

    'url_prefix' => UrlPrefix::normalize(env('EASELOGS_URL_PREFIX')),

    'demo_mode' => env('EASELOGS_DEMO_MODE', false),

    'demo' => [
        'upload_behavior' => env('EASELOGS_DEMO_UPLOAD_BEHAVIOR', 'enabled'),
        'allow_imports' => env('EASELOGS_DEMO_ALLOW_IMPORTS', false),
        'allow_deletes' => env('EASELOGS_DEMO_ALLOW_DELETES', false),
        'allow_account_changes' => env('EASELOGS_DEMO_ALLOW_ACCOUNT_CHANGES', false),
        'allow_registration' => env('EASELOGS_DEMO_ALLOW_REGISTRATION', false),
        'allow_password_reset' => env('EASELOGS_DEMO_ALLOW_PASSWORD_RESET', false),
        'allow_email_sending' => env('EASELOGS_DEMO_ALLOW_EMAIL_SENDING', false),
        'allow_external_webhooks' => env('EASELOGS_DEMO_ALLOW_EXTERNAL_WEBHOOKS', false),
        'show_public_notice' => env('EASELOGS_DEMO_SHOW_PUBLIC_NOTICE', true),

        'user' => [
            'name' => env('EASELOGS_DEMO_USER_NAME', 'Demo User'),
            'email' => env('EASELOGS_DEMO_USER_EMAIL', 'demo@easelogs.com'),
            'password' => env('EASELOGS_DEMO_USER_PASSWORD', 'change-this-demo-password'),
            'show_login_hint' => env('EASELOGS_DEMO_SHOW_LOGIN_HINT', true),
            'allow_one_click_login' => env('EASELOGS_DEMO_ALLOW_ONE_CLICK_LOGIN', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | EaseLogs Community Edition branding
    |--------------------------------------------------------------------------
    |
    | Display names for the hobbyist / self-hosted Community Edition branch.
    | Community Edition uses a reduced artwork field set (see docs when published).
    |
    */

    'short_name' => env('EASELOGS_SHORT_NAME', 'EaseLogs'),

    'edition' => env('EASELOGS_EDITION', 'Community Edition'),

    'display_name' => env(
        'EASELOGS_DISPLAY_NAME',
        env('APP_NAME', 'EaseLogs Community Edition')
    ),

    /*
    |--------------------------------------------------------------------------
    | Artwork photo uploads
    |--------------------------------------------------------------------------
    */

    'photo_max_kb' => (int) env('EASELOGS_PHOTO_MAX_KB', 10240),

    'photo_mimes' => ['jpeg', 'jpg', 'png', 'webp'],

    /*
    |--------------------------------------------------------------------------
    | Authentication extension point
    |--------------------------------------------------------------------------
    |
    | Core ships email/password login and first-run setup only.
    | Future OAuth providers (Google, Microsoft, Facebook, GitHub) register
    | implementations of App\Contracts\Auth\SocialLoginProvider here.
    | See docs/AUTH_EXTENSIONS.md.
    |
    */

    'auth' => [
        'social_providers' => [],
    ],

];
