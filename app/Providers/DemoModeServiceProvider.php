<?php

namespace App\Providers;

use App\Support\DemoMode;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DemoModeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (DemoMode::enabled() && ! DemoMode::allows('email_sending')) {
            config(['mail.default' => 'array']);
        }
    }

    public function boot(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event): ?bool {
            if (DemoMode::blocks('email_sending')) {
                return false;
            }

            return null;
        });

        Event::listen(NotificationSending::class, function (NotificationSending $event): ?bool {
            if ($event->channel === 'mail' && DemoMode::blocks('email_sending')) {
                return false;
            }

            return null;
        });
    }
}
