<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Backend Landing / Social Media OG Preview / Redirects
|--------------------------------------------------------------------------
*/

Route::get('/og/template/{slug}', [\App\Http\Controllers\Api\PublicOgController::class, 'templateOg']);
Route::get('/og/{slug}', [\App\Http\Controllers\Api\PublicOgController::class, 'weddingOg']);
Route::get('/public/og/template/{slug}', [\App\Http\Controllers\Api\PublicOgController::class, 'templateOg']);
Route::get('/public/og/{slug}', [\App\Http\Controllers\Api\PublicOgController::class, 'weddingOg']);
Route::get('/og-image/{slug}', [\App\Http\Controllers\Api\PublicOgController::class, 'renderWeddingOgImage']);
Route::get('/public/og-image/{slug}', [\App\Http\Controllers\Api\PublicOgController::class, 'renderWeddingOgImage']);

// Storage static asset streaming with CORS support
Route::get('/storage/{path}', function ($path) {
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    if (!$disk->exists($path)) {
        abort(404);
    }
    return $disk->response($path, null, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Access-Control-Allow-Headers' => '*',
        'Accept-Ranges' => 'bytes',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*');

// Fallback: Redirect any non-api, non-storage requests to Frontend SPA
Route::get('/{any}', function () {
    return redirect(env('FRONTEND_URL', 'https://ayohadir.id'));
})->where('any', '^(?!api|sanctum|storage|og).*$');
