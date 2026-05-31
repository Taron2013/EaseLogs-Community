<?php

use App\Http\Controllers\ArtworkController;
use App\Http\Controllers\ArtworkCsvController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SetupController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! User::query()->exists()) {
        return redirect()->route('setup.create');
    }

    return auth()->check()
        ? redirect()->route('artworks.index')
        : redirect()->route('login');
})->name('home');

Route::middleware('setup.available')->group(function (): void {
    Route::get('setup', [SetupController::class, 'create'])->name('setup.create');
    Route::post('setup', [SetupController::class, 'store'])->name('setup.store');
});

Route::middleware(['setup.complete', 'guest'])->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['setup.complete', 'auth'])->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('artworks/import-export', [ArtworkCsvController::class, 'importExport'])->name('artworks.import-export');
    Route::get('artworks/export/csv', [ArtworkCsvController::class, 'export'])->name('artworks.export.csv');
    Route::post('artworks/import/csv', [ArtworkCsvController::class, 'import'])->name('artworks.import.csv');

    Route::resource('artworks', ArtworkController::class);
});
