<?php

namespace App\Console\Commands;

use App\Support\DemoMode;
use Illuminate\Console\Command;

class DemoResetCommand extends Command
{
    protected $signature = 'easelogs:demo-reset {--force : Required to run in production}';

    protected $description = 'Reset the database and re-seed demo user and sample data';

    public function handle(): int
    {
        if (! DemoMode::enabled()) {
            $this->error('EASELOGS_DEMO_MODE is not enabled.');

            return self::FAILURE;
        }

        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to reset in production without --force.');

            return self::FAILURE;
        }

        $this->call('migrate:fresh', [
            '--force' => true,
        ]);

        $this->call('db:seed', [
            '--force' => true,
        ]);

        $this->info('Demo database reset complete.');

        return self::SUCCESS;
    }
}
