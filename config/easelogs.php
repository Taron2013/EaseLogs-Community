<?php

return [

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
