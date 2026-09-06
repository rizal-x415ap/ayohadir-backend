<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — Ayo Hadir</title>
    <meta name="description" content="{{ $description }}">

    <!-- Open Graph (Facebook, WhatsApp, Telegram) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:site_name" content="Ayo Hadir">

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
