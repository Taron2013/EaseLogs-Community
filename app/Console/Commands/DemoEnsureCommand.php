<?php

namespace App\Console\Commands;

use App\Support\DemoMode;
use App\Support\DemoUser;
use Illuminate\Console\Command;

class DemoEnsureCommand extends Command
{
    protected $signature = 'easelogs:demo-ensure';

    protected $description = 'Create or update the demo user and sample data when demo mode is enabled';

    public function handle(): int
    {
        if (! DemoMode::enabled()) {
            $this->error('EASELOGS_DEMO_MODE is not enabled.');

            return self::FAILURE;
        }

        if (! DemoUser::isConfigured()) {
            $this->error('Demo user email and password are not configured.');

            return self::FAILURE;
        }

        $user = DemoUser::ensureExists();
        DemoUser::seedSampleArtworks($user);

        $this->info('Demo user and sample data are ready ('.$user->email.').');

        return self::SUCCESS;
    }
}
