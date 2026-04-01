<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
