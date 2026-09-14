<?php

use App\Http\Controllers\AssetLookupController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Where the QR on an asset label points. The asset code is used directly so the
// URL still works if someone types it off a printed label.
Route::get('/a/{code}', AssetLookupController::class)
    ->name('asset.lookup')
    ->where('code', '[A-Za-z0-9\-\/]+');
