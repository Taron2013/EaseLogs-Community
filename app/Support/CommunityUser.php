<?php

namespace App\Support;

use App\Models\User;

class CommunityUser
{
    public const NAME = 'Community User';

    public const EMAIL = 'admin@easelogs.local';

    public const PASSWORD = 'password';

    public static function seedIfMissing(): void
    {
        if (User::query()->where('email', self::EMAIL)->exists()) {
            return;
        }

        User::factory()->create([
            'name' => self::NAME,
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ]);
    }

    public static function isDefaultAccountPresent(): bool
    {
        return User::query()->where('email', self::EMAIL)->exists();
    }
}
