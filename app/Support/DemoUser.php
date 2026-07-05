<?php

namespace App\Support;

use App\Models\Artwork;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class DemoUser
{
    public static function isConfigured(): bool
    {
        return DemoMode::enabled()
            && self::email() !== ''
            && self::password() !== '';
    }

    public static function name(): string
    {
        return (string) config('easelogs.demo.user.name', 'Demo User');
    }

    public static function email(): string
    {
        return strtolower(trim((string) config('easelogs.demo.user.email', '')));
    }

    public static function password(): string
    {
        return (string) config('easelogs.demo.user.password', '');
    }

    public static function showLoginHint(): bool
    {
        return DemoMode::enabled() && (bool) config('easelogs.demo.user.show_login_hint', false);
    }

    public static function allowsOneClickLogin(): bool
    {
        return DemoMode::enabled() && (bool) config('easelogs.demo.user.allow_one_click_login', false);
    }

    public static function isDemoUser(?User $user): bool
    {
        if ($user === null || ! DemoMode::enabled()) {
            return false;
        }

        return strcasecmp($user->email, self::email()) === 0;
    }

    public static function ensureExists(): User
    {
        if (! self::isConfigured()) {
            throw new \RuntimeException('Demo user is not configured. Enable EASELOGS_DEMO_MODE and set demo user env values.');
        }

        return User::query()->updateOrCreate(
            ['email' => self::email()],
            [
                'name' => self::name(),
                'password' => Hash::make(self::password()),
                'email_verified_at' => now(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function loginViewData(): array
    {
        return [
            'show_login_hint' => self::showLoginHint(),
            'allows_one_click_login' => self::allowsOneClickLogin(),
            'name' => self::name(),
            'email' => self::email(),
            'password' => self::password(),
        ];
    }

    /**
     * Seed a small fixed sample inventory for the demo account.
     */
    public static function seedSampleArtworks(User $user): void
    {
        if ($user->artworks()->exists()) {
            return;
        }

        $samples = [
            [
                'title' => 'Morning Light',
                'medium' => 'Oil on canvas',
                'height' => 24,
                'width' => 18,
                'dimension_unit' => 'in',
                'completed_date' => '2024-03-12',
                'notes' => 'Sample demo artwork for inventory browsing.',
            ],
            [
                'title' => 'Harbor Sketch',
                'medium' => 'Graphite on paper',
                'height' => 11,
                'width' => 14,
                'dimension_unit' => 'in',
                'start_date' => '2025-01-05',
                'notes' => 'Second sample entry in the public demo.',
            ],
            [
                'title' => 'Studio Still Life',
                'medium' => 'Acrylic',
                'height' => 16,
                'width' => 20,
                'dimension_unit' => 'in',
                'completed_date' => '2025-06-20',
            ],
        ];

        foreach ($samples as $attributes) {
            Artwork::query()->create(array_merge($attributes, ['user_id' => $user->id]));
        }
    }
}
