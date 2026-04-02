<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PlayDrive Admin</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<div class="flex min-h-screen items-center justify-center p-6">
    <div class="w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900 p-8 shadow-2xl">
        <div class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-400">PlayDrive</div>
        <h1 class="mt-3 text-3xl font-bold text-white">Admin login</h1>
        <p class="mt-2 text-slate-400">Enkel voor centraal tenantbeheer.</p>

        @if ($errors->any())
            <div class="mt-6 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.submit') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block space-y-2">
                <span class="text-sm text-slate-300">Gebruikersnaam</span>
                <input name="username" type="text" value="{{ old('username') }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500" required>
            </label>
            <label class="block space-y-2">
                <span class="text-sm text-slate-300">Paswoord</span>
                <input name="password" type="password" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500" required>
            </label>
            <button type="submit" class="w-full rounded-2xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500">Inloggen</button>
        </form>
    </div>
</div>
</body>
</html>
