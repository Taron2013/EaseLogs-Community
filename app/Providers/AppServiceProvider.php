<?php

namespace App\Providers;

use App\Support\DemoMode;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::middleware('web')
            ->prefix(config('easelogs.url_prefix'))
            ->group(function (): void {
                require base_path('routes/web.php');
            });

        // Compact text pagination (see resources/views/vendor/pagination/easelogs.blade.php).
        Paginator::defaultView('vendor.pagination.easelogs');
        Paginator::defaultSimpleView('vendor.pagination.easelogs');

        View::composer('*', function ($view): void {
            $view->with('easelogsDemo', DemoMode::viewData());
        });
    }
}
