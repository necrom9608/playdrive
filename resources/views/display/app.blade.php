<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PlayDrive Display</title>
    @vite(['resources/css/app.css', 'resources/js/apps/display/app.js'])

    <script>
        window.PlayDrive = {
            tenantName: @js($currentTenant?->name ?? null),
            tenantSlug: @js($currentTenant?->slug ?? null),
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
<body class="bg-slate-950">
<div id="app"></div>
</body>
</html>
