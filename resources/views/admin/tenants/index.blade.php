<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PlayDrive Tenantbeheer</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<div class="mx-auto max-w-7xl p-6">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-400">PlayDrive Admin</div>
            <h1 class="mt-3 text-3xl font-bold text-white">Tenantbeheer</h1>
            <p class="mt-2 text-slate-400">Beheer tenants en hun domeinen centraal via <span class="font-semibold text-white">/admin</span>.</p>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20">Uitloggen</button>
        </form>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-xl">
            <h2 class="text-xl font-semibold text-white">Nieuwe tenant</h2>
            <form method="POST" action="{{ route('admin.tenants.store') }}" class="mt-5 space-y-5">
                @csrf
                @include('admin.tenants.partials.form', ['tenant' => null])
                <button type="submit" class="rounded-2xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500">Tenant toevoegen</button>
            </form>
        </section>

        <section class="space-y-6">
            @foreach($tenants as $tenant)
                <article class="rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-xl">
                    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-semibold text-white">{{ $tenant->name }}</h2>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $tenant->is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300' }}">{{ $tenant->is_active ? 'Actief' : 'Inactief' }}</span>
                            </div>
                            <div class="mt-2 text-sm text-slate-400">Slug: <span class="font-semibold text-white">{{ $tenant->slug }}</span></div>
                            <div class="mt-1 text-sm text-slate-400">Primair domein: <span class="font-semibold text-white">{{ $tenant->primary_domain ?: '—' }}</span></div>
                        </div>
                        <form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}" onsubmit="return confirm('Tenant {{ $tenant->name }} verwijderen?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20">Verwijderen</button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('admin.tenants.update', $tenant) }}" class="space-y-5">
                        @csrf
                        @method('PUT')
                        @include('admin.tenants.partials.form', ['tenant' => $tenant])
                        <button type="submit" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">Tenant opslaan</button>
                    </form>
                </article>
            @endforeach
        </section>
    </div>
</div>
</body>
</html>
