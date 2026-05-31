<?php

use App\Http\Controllers\ArtworkController;
use App\Http\Controllers\ArtworkCsvController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/artworks');

Route::get('artworks/import-export', [ArtworkCsvController::class, 'importExport'])->name('artworks.import-export');
Route::get('artworks/export/csv', [ArtworkCsvController::class, 'export'])->name('artworks.export.csv');
Route::post('artworks/import/csv', [ArtworkCsvController::class, 'import'])->name('artworks.import.csv');

Route::resource('artworks', ArtworkController::class);
