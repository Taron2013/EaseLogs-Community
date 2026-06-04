<?php

namespace Database\Seeders;

use App\Support\DemoMode;
use App\Support\DemoUser;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! DemoMode::enabled() || ! DemoUser::isConfigured()) {
            return;
        }

        $user = DemoUser::ensureExists();
        DemoUser::seedSampleArtworks($user);
    }
}
