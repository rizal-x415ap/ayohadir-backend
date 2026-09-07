<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Backend Landing / Social Media OG Preview / Redirects
|--------------------------------------------------------------------------
*/

Route::get('/og/template/{slug}', function (string $slug) {
    $frontendUrl = rtrim(env('FRONTEND_URL', 'https://ayohadir.id'), '/');
    $canonicalUrl = "{$frontendUrl}/templates/{$slug}";

    $template = \App\Models\Template::where('slug', $slug)->first();
    if (!$template) {
        return redirect($canonicalUrl);
    }

    $title = "Template Undangan: {$template->name} — Ayo Hadir";
    $description = $template->description ?: "Pratinjau tema undangan pernikahan digital {$template->name} di Ayo Hadir.";

    // Extract couple photo from schema if available
    $image = $template->thumbnail;
    if (!empty($template->schema['desktopCover']['elements'])) {
        foreach ($template->schema['desktopCover']['elements'] as $el) {
            if (($el['bindingKey'] ?? '') === 'couple.couplePhotoUrl') {
                $image = $el['props']['url'] ?? $el['url'] ?? $image;
                break;
            }
        }
    }

    $primaryColor = $template->schema['theme']['colors']['primary'] ?? '#03AC0E';

    return response()->view('og-meta', [
        'title' => $title,
        'description' => $description,
        'image' => $image ?: "{$frontendUrl}/images/og-ayohadir.png",
        'url' => $canonicalUrl,
        'frontendUrl' => $canonicalUrl,
        'themeColor' => $primaryColor,
    ]);
});

Route::get('/og/{slug}', function (string $slug, \Illuminate\Http\Request $request) {
    $frontendUrl = rtrim(env('FRONTEND_URL', 'https://ayohadir.id'), '/');
    $guestParam = trim((string) ($request->query('to') ?? $request->query('guest') ?? ''));
    $canonicalUrl = "{$frontendUrl}/{$slug}" . (!empty($guestParam) ? '?to=' . urlencode($guestParam) : '');

    $wedding = \App\Models\Wedding::where('slug', $slug)->first();

    if (!$wedding) {
        return redirect($canonicalUrl);
    }

    $bride = $wedding->bride_name ?? 'Mempelai Wanita';
    $groom = $wedding->groom_name ?? 'Mempelai Pria';
    $couple = trim("{$bride} & {$groom}");

    $title = !empty($guestParam)
        ? "Undangan Pernikahan untuk {$guestParam} — {$couple}"
        : "The Wedding of {$couple} — Ayo Hadir";

    $dateStr = $wedding->wedding_date ? $wedding->wedding_date->translatedFormat('l, d F Y') : null;
    $venue = $wedding->venue_name ? " di {$wedding->venue_name}" : '';

    $description = !empty($guestParam)
        ? "Kepada Yth. {$guestParam}, tanpa mengurangi rasa hormat, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri pernikahan kami" . ($dateStr ? " pada {$dateStr}{$venue}." : '.')
        : "Tanpa mengurangi rasa hormat, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri pernikahan {$couple}" . ($dateStr ? " pada {$dateStr}{$venue}." : '.') . " Buka undangan digital di sini.";

    // Resolve couple photo
    $image = $wedding->custom_content['couple.couplePhotoUrl']
        ?? $wedding->custom_content['couple']['couplePhotoUrl']
        ?? $wedding->cover_image_url
        ?? "{$frontendUrl}/images/og-ayohadir.png";

    $primaryColor = $wedding->design?->schema['theme']['colors']['primary'] ?? '#03AC0E';

    return response()->view('og-meta', [
        'title' => $title,
        'description' => $description,
        'image' => $image,
        'url' => $canonicalUrl,
        'frontendUrl' => $canonicalUrl,
        'themeColor' => $primaryColor,
    ]);
});

// Fallback: Redirect any non-api, non-storage requests to Frontend SPA
Route::get('/{any}', function () {
    return redirect(env('FRONTEND_URL', 'https://ayohadir.id'));
})->where('any', '^(?!api|sanctum|storage|og).*$');
