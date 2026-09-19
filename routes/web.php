<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Backend Landing / Social Media OG Preview / Redirects
|--------------------------------------------------------------------------
*/

Route::get('/og/template/{slug}', [\App\Http\Controllers\Api\PublicOgController::class, 'templateOg']);
Route::get('/og/{slug}', [\App\Http\Controllers\Api\PublicOgController::class, 'weddingOg']);

// Fallback: Redirect any non-api, non-storage requests to Frontend SPA
Route::get('/{any}', function () {
    return redirect(env('FRONTEND_URL', 'https://ayohadir.id'));
})->where('any', '^(?!api|sanctum|storage|og).*$');
