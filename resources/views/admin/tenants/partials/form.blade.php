@php
    $domains = old('domains', $tenant?->domains?->map(fn($domain) => [
        'id' => $domain->id,
        'domain' => $domain->domain,
        'app_type' => $domain->app_type,
        'is_primary' => $domain->is_primary,
    ])->values()->all() ?? [['domain' => '', 'app_type' => 'frontdesk', 'is_primary' => true]]);
    if (count($domains) < 6) {
        $domains = array_pad($domains, 6, ['domain' => '', 'app_type' => 'frontdesk', 'is_primary' => false]);
    }
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <label class="block space-y-2">
        <span class="text-sm text-slate-300">Naam</span>
        <input name="name" type="text" value="{{ old('name', $tenant?->name) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500" required>
    </label>
    <label class="block space-y-2">
        <span class="text-sm text-slate-300">Bedrijfsnaam</span>
        <input name="company_name" type="text" value="{{ old('company_name', $tenant?->company_name) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500">
    </label>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <label class="block space-y-2">
        <span class="text-sm text-slate-300">Slug</span>
        <input name="slug" type="text" value="{{ old('slug', $tenant?->slug) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500" required>
    </label>
    <label class="block space-y-2">
        <span class="text-sm text-slate-300">E-mail</span>
        <input name="email" type="email" value="{{ old('email', $tenant?->email) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500">
    </label>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <label class="block space-y-2">
        <span class="text-sm text-slate-300">Telefoon</span>
        <input name="phone" type="text" value="{{ old('phone', $tenant?->phone) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500">
    </label>
    <label class="block space-y-2">
        <span class="text-sm text-slate-300">BTW-nummer</span>
        <input name="vat_number" type="text" value="{{ old('vat_number', $tenant?->vat_number) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500">
    </label>
</div>

<div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_140px]">
    <label class="block space-y-2">
        <span class="text-sm text-slate-300">Straat</span>
        <input name="street" type="text" value="{{ old('street', $tenant?->street) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500">
    </label>
    <label class="block space-y-2">
        <span class="text-sm text-slate-300">Nummer</span>
        <input name="number" type="text" value="{{ old('number', $tenant?->number) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500">
    </label>
</div>

<div class="grid gap-4 md:grid-cols-3">
    <label class="block space-y-2 md:col-span-1">
        <span class="text-sm text-slate-300">Postcode</span>
        <input name="postal_code" type="text" value="{{ old('postal_code', $tenant?->postal_code) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500">
    </label>
    <label class="block space-y-2 md:col-span-1">
        <span class="text-sm text-slate-300">Stad</span>
        <input name="city" type="text" value="{{ old('city', $tenant?->city) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500">
    </label>
    <label class="block space-y-2 md:col-span-1">
        <span class="text-sm text-slate-300">Land</span>
        <input name="country" type="text" value="{{ old('country', $tenant?->country) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500">
    </label>
</div>

<label class="block space-y-2">
    <span class="text-sm text-slate-300">Receipt footer</span>
    <textarea name="receipt_footer" rows="3" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500">{{ old('receipt_footer', $tenant?->receipt_footer) }}</textarea>
</label>

<label class="flex items-center gap-3 text-sm text-slate-300">
    <input name="is_active" type="hidden" value="0">
    <input name="is_active" type="checkbox" value="1" class="h-4 w-4 rounded border-slate-600 bg-slate-950" @checked(old('is_active', $tenant?->is_active ?? true))>
    Tenant actief
</label>

<div class="space-y-3 rounded-2xl border border-slate-800 bg-slate-950/50 p-4">
    <div>
        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-400">Domeinen</h3>
        <p class="mt-1 text-xs text-slate-500">Vul per regel een domein in en kies het type app. De eerste ingevulde regel wordt automatisch het primaire domein.</p>
    </div>
    @foreach($domains as $index => $domain)
        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px]">
            <input type="hidden" name="domains[{{ $index }}][id]" value="{{ $domain['id'] ?? '' }}">
            <input name="domains[{{ $index }}][domain]" type="text" value="{{ $domain['domain'] ?? '' }}" placeholder="bijv. game-inn.playdrive.be" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500">
            <select name="domains[{{ $index }}][app_type]" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500">
                @foreach($appTypes as $appType)
                    <option value="{{ $appType }}" @selected(($domain['app_type'] ?? 'frontdesk') === $appType)>{{ $appType }}</option>
                @endforeach
            </select>
        </div>
    @endforeach
</div>
