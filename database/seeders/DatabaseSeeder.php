<?php

namespace Database\Seeders;

use App\Support\DemoMode;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Production installs use first-run setup (/setup) instead of a default user.
     * Add optional dev/demo seeders here when needed.
     */
    public function run(): void
    {
        if (DemoMode::enabled()) {
            $this->call(DemoSeeder::class);
        }
    }
}
