<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ str_contains($title, 'Ayo Hadir') ? $title : "{$title} — Ayo Hadir" }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="theme-color" content="{{ $themeColor ?? '#03AC0E' }}">

    <!-- Open Graph (Facebook, WhatsApp, Telegram, LinkedIn) -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Ayo Hadir">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:image:secure_url" content="{{ $image }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">

    <!-- Auto-redirect browser visitors to the frontend SPA -->
    <meta http-equiv="refresh" content="0; url={{ $frontendUrl }}">
</head>
<body>
    <p>Mengalihkan ke undangan... <a href="{{ $frontendUrl }}">Klik di sini</a> jika tidak otomatis teralihkan.</p>
</body>
</html>
