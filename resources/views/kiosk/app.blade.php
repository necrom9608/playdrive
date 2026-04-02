<!DOCTYPE html>
<html lang="nl">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logos/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/logos/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/images/logos/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/images/logos/icon-512.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlayDrive Kiosk</title>
    @vite(['resources/css/app.css', 'resources/js/apps/kiosk/app.js'])

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
