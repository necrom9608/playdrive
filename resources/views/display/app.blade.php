<!DOCTYPE html>
<html lang="nl">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logos/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/logos/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/images/logos/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/images/logos/icon-512.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#020617">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PlayDrive Display">
    <title>PlayDrive Display</title>
    @vite(['resources/css/app.css', 'resources/js/apps/display/app.js'])

    <script>
        window.PlayDrive = {
            tenantName: @js($currentTenant?->name ?? null),
            tenantSlug: @js($currentTenant?->slug ?? null),
            tenantLogoUrl: @js($currentTenant?->logo_path ? route('display.tenant-logo', ['v' => optional($currentTenant?->updated_at)->timestamp ?: time()]) : null),
            host: @js(request()->getHost()),
            realtime: {
                appKey: @js(config('broadcasting.connections.reverb.key', 'playdrive')),
                host: @js(env('VITE_REVERB_HOST', request()->getHost())),
                port: @js((int) env('VITE_REVERB_PORT', env('REVERB_PORT', 8080))),
                scheme: @js(env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', request()->isSecure() ? 'https' : 'http'))),
            },
        };
    </script>
</head>
<body class="bg-slate-950 overscroll-none select-none">
<div id="app"></div>
</body>
</html>
