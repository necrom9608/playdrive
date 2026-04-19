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

        $stats = [
            'total' => $tenants->count(),
            'active' => $tenants->where('is_active', true)->count(),
            'inactive' => $tenants->where('is_active', false)->count(),
            'domains' => $tenants->sum(fn (Tenant $tenant) => $tenant->domains->count()),
        ];

        return view('admin.tenants.index', [
            'tenants' => $tenants,
            'appTypes' => $this->appTypes(),
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTenant($request);

        DB::transaction(function () use ($data) {
            $tenant = Tenant::query()->create($this->tenantPayload($data));
            $this->syncDomains($tenant, $data['domains']);
        });

        return redirect()->route('admin.tenants.index')->with('status', 'Tenant toegevoegd.');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $this->validateTenant($request, $tenant);

        DB::transaction(function () use ($tenant, $data) {
            $tenant->update($this->tenantPayload($data));
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
            'company_name' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tenants', 'slug')->ignore($tenant?->id),
            ],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
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
            return back()->withErrors(['domains' => 'Voeg minstens één domein toe aan de tenant.'])->withInput()->throwResponse();
        }

        foreach ($domains as $index => $domain) {
            $query = TenantDomain::query()->whereRaw('lower(domain) = ?', [$domain['domain']]);

            if ($tenant) {
                $query->where('tenant_id', '!=', $tenant->id);
            }

            if ($query->exists()) {
                return back()->withErrors([
                    "domains.$index.domain" => 'Domein "' . $domain['domain'] . '" is al gekoppeld aan een andere tenant.',
                ])->withInput()->throwResponse();
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

    private function tenantPayload(array $data): array
    {
        return [
            'name' => $data['name'],
            'company_name' => $this->nullableString($data['company_name'] ?? null),
            'slug' => Str::slug($data['slug'] ?: $data['name']),
            'primary_domain' => $this->firstPrimaryDomain($data['domains']),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'email' => $this->nullableString($data['email'] ?? null),
            'phone' => $this->nullableString($data['phone'] ?? null),
            'vat_number' => $this->nullableString($data['vat_number'] ?? null),
            'street' => $this->nullableString($data['street'] ?? null),
            'number' => $this->nullableString($data['number'] ?? null),
            'postal_code' => $this->nullableString($data['postal_code'] ?? null),
            'city' => $this->nullableString($data['city'] ?? null),
            'country' => $this->nullableString($data['country'] ?? null),
            'receipt_footer' => $this->nullableString($data['receipt_footer'] ?? null),
        ];
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
