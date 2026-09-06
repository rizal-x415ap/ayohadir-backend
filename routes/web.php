<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Backend Landing / Social Media OG Preview / Redirects
|--------------------------------------------------------------------------
*/

Route::get('/og/{slug}', function (string $slug) {
    $wedding = \App\Models\Wedding::whereHas('invitations', function ($q) use ($slug) {
        $q->where('slug', $slug);
    })->first();

    $frontendUrl = rtrim(env('FRONTEND_URL', 'https://ayohadir.id'), '/');
    $canonicalUrl = $frontendUrl . '/' . $slug;

    if (!$wedding) {
        return redirect($canonicalUrl);
    }

    $title = trim(($wedding->bride_name ?? '') . ' & ' . ($wedding->groom_name ?? ''));
    $description = 'Undangan Pernikahan ' . ($title ?: 'Digital');
    $image = $wedding->cover_image_url ?? '';

    return response()->view('og-meta', [
        'title' => $title ?: 'Undangan Pernikahan',
        'description' => $description,
        'image' => $image,
        'url' => $canonicalUrl,
        'frontendUrl' => $canonicalUrl,
    ]);
});

// Fallback: Redirect any non-api, non-storage requests to Frontend SPA
Route::get('/{any}', function () {
    return redirect(env('FRONTEND_URL', 'https://ayohadir.id'));
})->where('any', '^(?!api|sanctum|storage|og).*$');
