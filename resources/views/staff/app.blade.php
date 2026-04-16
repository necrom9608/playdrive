<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#020617">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PlayDrive Staff">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logos/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/logos/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/images/logos/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/images/logos/icon-512.png">
    <link rel="manifest" href="/staff.webmanifest?v=3" crossorigin="use-credentials">
    <title>PlayDrive Staff</title>
    @vite(['resources/css/app.css', 'resources/js/apps/staff/app.js'])
    <script>
        window.PlayDrive = {
            tenantName: @js($currentTenant?->name ?? null),
            tenantSlug: @js($currentTenant?->slug ?? null),
            host: @js(request()->getHost()),
        };
    </script>
</head>
<body class="bg-slate-950">
<div id="app"></div>
</body>
</html>
