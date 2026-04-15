<!DOCTYPE html>
<html lang="nl">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logos/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/logos/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/images/logos/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/images/logos/icon-512.png">
    <link rel="manifest" href="/display.webmanifest">
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
            displayConfigPin: @js(env('DISPLAY_CONFIG_PIN', '2580')),
            realtime: {
                appKey: @js(config('broadcasting.realtime.app_key', 'playdrive')),
                host: @js(config('broadcasting.realtime.host', request()->getHost())),
                port: @js((int) config('broadcasting.realtime.port', request()->isSecure() ? 443 : 80)),
                scheme: @js(config('broadcasting.realtime.scheme', request()->isSecure() ? 'https' : 'http')),
            },
        };
    </script>
</head>
<body class="bg-slate-950 overscroll-none select-none">
<div id="app"></div>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw-display.js')
                .then((registration) => {
                    console.log('Service Worker registered', registration);
                })
                .catch((error) => {
                    console.error('Service Worker failed', error);
                });
        });
    }
</script>


</body>
</html>
