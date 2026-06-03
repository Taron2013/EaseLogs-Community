<?php

use App\Http\Middleware\EnsureSetupIsAvailable;
use App\Http\Middleware\EnsureSetupIsComplete;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'setup.complete' => EnsureSetupIsComplete::class,
            'setup.available' => EnsureSetupIsAvailable::class,
        ]);

        $middleware->redirectGuestsTo(function () {
            if (! User::query()->exists()) {
                return route('setup.create');
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
