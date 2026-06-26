<?php

use App\Http\Controllers\ArtworkController;
use App\Http\Controllers\ArtworkCsvController;
use App\Http\Controllers\ArtworkPhotoBulkImportController;
use App\Http\Controllers\DemoLoginController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProfileController;
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
    Route::post('setup', [SetupController::class, 'store'])
        ->middleware('restrict.demo:registration')
        ->name('setup.store');
});

Route::middleware(['setup.complete', 'guest'])->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::post('login/demo', [DemoLoginController::class, 'store'])->name('login.demo');
});

Route::middleware(['setup.complete', 'auth'])->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])
        ->middleware('restrict.demo:account_changes')
        ->name('profile.update');
    Route::get('profile/password', [ProfileController::class, 'editPassword'])->name('profile.password.edit');
    Route::patch('profile/password', [ProfileController::class, 'updatePassword'])
        ->middleware('restrict.demo:account_changes')
        ->name('profile.password.update');

    Route::get('artworks/import-export', [ArtworkCsvController::class, 'importExport'])->name('artworks.import-export');
    Route::get('artworks/export/csv', [ArtworkCsvController::class, 'export'])->name('artworks.export.csv');
    Route::post('artworks/import/csv', [ArtworkCsvController::class, 'import'])
        ->middleware('restrict.demo:imports')
        ->name('artworks.import.csv');

    Route::post('artworks/import/photos/preview', [ArtworkPhotoBulkImportController::class, 'preview'])
        ->middleware('restrict.demo:imports')
        ->name('artworks.photo-bulk-import.preview');
    Route::get('artworks/import/photos/preview/{token}', [ArtworkPhotoBulkImportController::class, 'showPreview'])
        ->name('artworks.photo-bulk-import.preview.show');
    Route::get('artworks/import/photos/preview/{token}/thumb/{rowKey}', [ArtworkPhotoBulkImportController::class, 'thumbnail'])
        ->name('artworks.photo-bulk-import.preview.thumb');
    Route::get('artworks/import/photos/preview/{token}/search', [ArtworkPhotoBulkImportController::class, 'searchArtworks'])
        ->name('artworks.photo-bulk-import.preview.search');
    Route::post('artworks/import/photos/preview/{token}/resolve', [ArtworkPhotoBulkImportController::class, 'resolveMatch'])
        ->name('artworks.photo-bulk-import.preview.resolve');
    Route::post('artworks/import/photos/preview/{token}/undo-resolve', [ArtworkPhotoBulkImportController::class, 'undoResolve'])
        ->name('artworks.photo-bulk-import.preview.undo-resolve');
    Route::post('artworks/import/photos/apply', [ArtworkPhotoBulkImportController::class, 'apply'])
        ->middleware('restrict.demo:uploads')
        ->name('artworks.photo-bulk-import.apply');
    Route::get('artworks/import/photos/discard/{token}', [ArtworkPhotoBulkImportController::class, 'discard'])
        ->name('artworks.photo-bulk-import.discard');

    Route::delete('artworks/bulk-delete', [ArtworkController::class, 'bulkDestroy'])
        ->middleware('restrict.demo:deletes')
        ->name('artworks.bulk-delete');

    Route::resource('artworks', ArtworkController::class)
        ->middlewareFor(['store', 'update'], 'restrict.demo:uploads')
        ->middlewareFor('destroy', 'restrict.demo:deletes');

    /*
    |--------------------------------------------------------------------------
    | Demo mode middleware map (future routes)
    |--------------------------------------------------------------------------
    |
    | When adding routes, apply restrict.demo or DemoOutbound guards:
    | - Password reset (forgot/reset): restrict.demo:password_reset
    | - Outbound mail in controllers: DemoOutbound::ensureEmailAllowed()
    | - Webhooks: DemoOutbound::ensureWebhookAllowed()
    | - Pro/Enterprise file or document upload: DemoOutbound::ensureProFileWriteAllowed()
    | - Pro/Enterprise file or document delete: DemoOutbound::ensureProFileDeleteAllowed()
    | - Payments/licensing: DemoOutbound::ensurePaymentActionAllowed()
    |
    | Artwork metadata create/update stays allowed in public demo; uploads use
    | restrict.demo:uploads (disabled = 403, discard = strip file before controller).
    |
    | Web routes are prefixed in bootstrap/app.php via config('easelogs.url_prefix').
    */
});
