<!DOCTYPE html>
<html lang="nl">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logos/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/logos/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/images/logos/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/images/logos/icon-512.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ongeldige tenant</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<div class="flex min-h-screen items-center justify-center p-6">
    <div class="w-full max-w-xl rounded-3xl border border-slate-800 bg-slate-900 p-8 shadow-2xl">
        <div class="inline-flex rounded-full border border-rose-500/30 bg-rose-500/10 px-3 py-1 text-xs font-semibold text-rose-200">Geen geldige tenant</div>
        <h1 class="mt-5 text-3xl font-bold text-white">Deze host is niet gekoppeld aan een tenant.</h1>
        <p class="mt-3 text-slate-300">Host: <span class="font-semibold text-white">{{ $host }}</span></p>
        <p class="mt-4 text-slate-400">Controleer of het subdomein correct bestaat en gekoppeld is in het tenantbeheer.</p>
    </div>
</div>
</body>
</html>
