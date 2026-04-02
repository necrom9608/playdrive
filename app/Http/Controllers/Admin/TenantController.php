<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::query()
            ->with(['domains' => fn ($query) => $query->orderBy('app_type')->orderByDesc('is_primary')->orderBy('domain')])
            ->orderBy('name')
            ->get();

        return view('admin.tenants.index', [
            'tenants' => $tenants,
            'appTypes' => $this->appTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTenant($request);

        DB::transaction(function () use ($data) {
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'slug' => Str::slug($data['slug'] ?: $data['name']),
                'primary_domain' => $this->firstPrimaryDomain($data['domains']),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->syncDomains($tenant, $data['domains']);
        });

        return redirect()->route('admin.tenants.index')->with('status', 'Tenant toegevoegd.');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $this->validateTenant($request, $tenant);

        DB::transaction(function () use ($tenant, $data) {
            $tenant->update([
                'name' => $data['name'],
                'slug' => Str::slug($data['slug'] ?: $data['name']),
                'primary_domain' => $this->firstPrimaryDomain($data['domains']),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->syncDomains($tenant, $data['domains']);
        });

        return redirect()->route('admin.tenants.index')->with('status', 'Tenant bijgewerkt.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        DB::transaction(function () use ($tenant) {
            TenantDomain::query()->where('tenant_id', $tenant->id)->delete();
            $tenant->delete();
        });

        return redirect()->route('admin.tenants.index')->with('status', 'Tenant verwijderd.');
    }

    private function validateTenant(Request $request, ?Tenant $tenant = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tenants', 'slug')->ignore($tenant?->id),
            ],
            'is_active' => ['nullable', 'boolean'],
            'domains' => ['nullable', 'array'],
            'domains.*.id' => ['nullable', 'integer'],
            'domains.*.domain' => ['required', 'string', 'max:255'],
            'domains.*.app_type' => ['required', Rule::in($this->appTypes())],
            'domains.*.is_primary' => ['nullable', 'boolean'],
        ]);

        $domains = collect($data['domains'] ?? [])
            ->map(function (array $domain) {
                return [
                    'id' => $domain['id'] ?? null,
                    'domain' => strtolower(trim($domain['domain'])),
                    'app_type' => $domain['app_type'],
                    'is_primary' => (bool) ($domain['is_primary'] ?? false),
                ];
            })
            ->filter(fn (array $domain) => $domain['domain'] !== '')
            ->values()
            ->all();

        if (count($domains) === 0) {
            abort(422, 'Voeg minstens één domein toe aan de tenant.');
        }

        foreach ($domains as $index => $domain) {
            $query = TenantDomain::query()->where('domain', $domain['domain']);

            if ($tenant) {
                $query->where('tenant_id', '!=', $tenant->id);
            }

            if ($query->exists()) {
                abort(422, 'Domein "' . $domain['domain'] . '" is al gekoppeld aan een andere tenant.');
            }

            if ($index === 0) {
                $domains[$index]['is_primary'] = true;
            }
        }

        $data['domains'] = $domains;
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }

    private function syncDomains(Tenant $tenant, array $domains): void
    {
        $existingIds = [];

        foreach ($domains as $index => $domain) {
            $record = TenantDomain::query()->updateOrCreate(
                [
                    'id' => $domain['id'] ?? null,
                    'tenant_id' => $tenant->id,
                ],
                [
                    'domain' => $domain['domain'],
                    'app_type' => $domain['app_type'],
                    'is_primary' => $index === 0,
                ]
            );

            $existingIds[] = $record->id;
        }

        TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('id', $existingIds)
            ->delete();
    }

    private function firstPrimaryDomain(array $domains): ?string
    {
        return $domains[0]['domain'] ?? null;
    }

    private function appTypes(): array
    {
        return ['frontdesk', 'backoffice', 'kiosk', 'client', 'staff', 'display'];
    }
}
