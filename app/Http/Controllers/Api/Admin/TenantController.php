<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index(): JsonResponse
    {
        $tenants = Tenant::query()
            ->with(['domains' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('app_type')->orderBy('domain')])
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant) => $this->mapTenant($tenant));

        return response()->json(['tenants' => $tenants]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateTenant($request);

        $tenant = DB::transaction(function () use ($data) {
            $tenant = Tenant::query()->create($this->tenantPayload($data));
            $this->syncDomains($tenant, $data['domains']);
            return $tenant;
        });

        $tenant->load('domains');

        return response()->json(['tenant' => $this->mapTenant($tenant)], 201);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $this->validateTenant($request, $tenant);

        DB::transaction(function () use ($tenant, $data) {
            $tenant->update($this->tenantPayload($data));
            $this->syncDomains($tenant, $data['domains']);
        });

        $tenant->load('domains');

        return response()->json(['tenant' => $this->mapTenant($tenant)]);
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        DB::transaction(function () use ($tenant) {
            TenantDomain::query()->where('tenant_id', $tenant->id)->delete();
            $tenant->delete();
        });

        return response()->json(['ok' => true]);
    }

    private function validateTenant(Request $request, ?Tenant $tenant = null): array
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'company_name'    => ['nullable', 'string', 'max:255'],
            'slug'            => ['required', 'string', 'max:255', Rule::unique('tenants', 'slug')->ignore($tenant?->id)],
            'email'           => ['nullable', 'email:rfc', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:255'],
            'vat_number'      => ['nullable', 'string', 'max:255'],
            'street'          => ['nullable', 'string', 'max:255'],
            'number'          => ['nullable', 'string', 'max:255'],
            'postal_code'     => ['nullable', 'string', 'max:255'],
            'city'            => ['nullable', 'string', 'max:255'],
            'country'         => ['nullable', 'string', 'max:255'],
            'receipt_footer'  => ['nullable', 'string', 'max:1000'],
            'is_active'       => ['nullable', 'boolean'],
            'domains'         => ['nullable', 'array'],
            'domains.*.id'    => ['nullable', 'integer'],
            'domains.*.domain'   => ['required_with:domains', 'string', 'max:255'],
            'domains.*.app_type' => ['required_with:domains', Rule::in($this->appTypes())],
        ]);

        $domains = collect($data['domains'] ?? [])
            ->filter(fn (array $d) => trim($d['domain'] ?? '') !== '')
            ->values()
            ->map(fn (array $d, int $i) => [
                'id'         => $d['id'] ?? null,
                'domain'     => strtolower(trim($d['domain'])),
                'app_type'   => $d['app_type'],
                'is_primary' => $i === 0,
            ])
            ->all();

        foreach ($domains as $index => $domain) {
            $conflict = TenantDomain::query()
                ->whereRaw('lower(domain) = ?', [$domain['domain']])
                ->when($tenant, fn ($q) => $q->where('tenant_id', '!=', $tenant->id))
                ->exists();

            if ($conflict) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "domains.$index.domain" => ['Domein "' . $domain['domain'] . '" is al in gebruik door een andere tenant.'],
                ]);
            }
        }

        $data['domains']   = $domains;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    private function syncDomains(Tenant $tenant, array $domains): void
    {
        $keptIds = [];

        foreach ($domains as $index => $domain) {
            $record = TenantDomain::query()->updateOrCreate(
                ['id' => $domain['id'] ?: null, 'tenant_id' => $tenant->id],
                ['domain' => $domain['domain'], 'app_type' => $domain['app_type'], 'is_primary' => $index === 0],
            );
            $keptIds[] = $record->id;
        }

        TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    private function tenantPayload(array $data): array
    {
        return [
            'name'           => $data['name'],
            'company_name'   => $this->nullable($data['company_name'] ?? null),
            'slug'           => Str::slug($data['slug'] ?: $data['name']),
            'primary_domain' => $data['domains'][0]['domain'] ?? null,
            'is_active'      => (bool) ($data['is_active'] ?? true),
            'email'          => $this->nullable($data['email'] ?? null),
            'phone'          => $this->nullable($data['phone'] ?? null),
            'vat_number'     => $this->nullable($data['vat_number'] ?? null),
            'street'         => $this->nullable($data['street'] ?? null),
            'number'         => $this->nullable($data['number'] ?? null),
            'postal_code'    => $this->nullable($data['postal_code'] ?? null),
            'city'           => $this->nullable($data['city'] ?? null),
            'country'        => $this->nullable($data['country'] ?? null),
            'receipt_footer' => $this->nullable($data['receipt_footer'] ?? null),
        ];
    }

    private function mapTenant(Tenant $tenant): array
    {
        return [
            'id'             => $tenant->id,
            'name'           => $tenant->name,
            'company_name'   => $tenant->company_name,
            'slug'           => $tenant->slug,
            'primary_domain' => $tenant->primary_domain,
            'email'          => $tenant->email,
            'phone'          => $tenant->phone,
            'vat_number'     => $tenant->vat_number,
            'street'         => $tenant->street,
            'number'         => $tenant->number,
            'postal_code'    => $tenant->postal_code,
            'city'           => $tenant->city,
            'country'        => $tenant->country,
            'receipt_footer' => $tenant->receipt_footer,
            'is_active'      => (bool) $tenant->is_active,
            'domains'        => $tenant->domains->map(fn (TenantDomain $d) => [
                'id'         => $d->id,
                'domain'     => $d->domain,
                'app_type'   => $d->app_type,
                'is_primary' => (bool) $d->is_primary,
            ])->values()->all(),
        ];
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function appTypes(): array
    {
        return ['frontdesk', 'backoffice', 'kiosk', 'client', 'staff', 'display'];
    }
}
