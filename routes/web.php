<?php

use App\Http\Controllers\ArtworkController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/artworks');

Route::resource('artworks', ArtworkController::class);
