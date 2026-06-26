<?php

use App\Http\Middleware\EnsureSetupIsAvailable;
use App\Http\Middleware\EnsureSetupIsComplete;
use App\Http\Middleware\RestrictDemoMode;
use App\Models\User;
use App\Support\ArtworkPhotoBulkImport\PhotoImportUploadEnvironment;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/health/photo-import-upload', function () {
                $report = PhotoImportUploadEnvironment::report();
                $statusCode = $report['status'] === 'misconfigured' ? 503 : 200;

                return response()->json($report, $statusCode);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'setup.complete' => EnsureSetupIsComplete::class,
            'setup.available' => EnsureSetupIsAvailable::class,
            'restrict.demo' => RestrictDemoMode::class,
        ]);

        $middleware->preventRequestsDuringMaintenance(except: [
            '/up',
            '/health/photo-import-upload',
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
