<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantSettingsController extends Controller
{
    public function show(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        return response()->json([
            'data' => $this->mapTenant($currentTenant->tenant->fresh()),
        ]);
    }

    public function update(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $tenant = $currentTenant->tenant;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'receipt_footer' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_logo') && $tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
            $tenant->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($tenant->logo_path) {
                Storage::disk('public')->delete($tenant->logo_path);
            }

            $tenant->logo_path = $request->file('logo')->store("tenant-settings/tenant-{$tenant->id}", 'public');
        }

        $tenant->fill([
            'name' => $data['name'],
            'company_name' => $data['company_name'] ?? null,
            'street' => $data['street'] ?? null,
            'number' => $data['number'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'vat_number' => $data['vat_number'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'receipt_footer' => $data['receipt_footer'] ?? null,
        ]);
        $tenant->save();

        return response()->json([
            'message' => 'Tenantinstellingen werden opgeslagen.',
            'data' => $this->mapTenant($tenant->fresh()),
        ]);
    }

    private function mapTenant($tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'company_name' => $tenant->company_name,
            'display_name' => $tenant->display_name,
            'street' => $tenant->street,
            'number' => $tenant->number,
            'postal_code' => $tenant->postal_code,
            'city' => $tenant->city,
            'country' => $tenant->country,
            'full_address' => $tenant->full_address,
            'vat_number' => $tenant->vat_number,
            'phone' => $tenant->phone,
            'email' => $tenant->email,
            'logo_path' => $tenant->logo_path,
            'logo_url' => $tenant->logo_url,
            'receipt_footer' => $tenant->receipt_footer,
        ];
    }
}
