<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Invoicing\OrderInvoiceService;
use App\Domain\Invoicing\WeFactClient;
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
            'family_max_adults' => ['nullable', 'integer', 'min:1', 'max:50'],
            'family_max_children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'newsletter_url' => ['nullable', 'url', 'max:255'],
            'newsletter_token' => ['nullable', 'string', 'max:255'],
            'clear_newsletter_token' => ['nullable', 'boolean'],
            'wefact_api_key' => ['nullable', 'string', 'max:255'],
            'clear_wefact_api_key' => ['nullable', 'boolean'],
            'wefact_tax_21' => ['nullable', 'string', 'max:20'],
            'wefact_tax_12' => ['nullable', 'string', 'max:20'],
            'wefact_tax_6' => ['nullable', 'string', 'max:20'],
            'wefact_tax_0' => ['nullable', 'string', 'max:20'],
            'test_wefact' => ['nullable', 'boolean'],
            'wefact_payment_term' => ['nullable', 'integer', 'min:0', 'max:120'],
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
            // leeg = onbeperkt
            'family_max_adults' => filled($data['family_max_adults'] ?? null) ? (int) $data['family_max_adults'] : null,
            'family_max_children' => filled($data['family_max_children'] ?? null) ? (int) $data['family_max_children'] : null,
            'newsletter_url' => filled($data['newsletter_url'] ?? null) ? trim($data['newsletter_url']) : null,
        ]);

        // Token is write-only: leeg laten = behouden.
        if ($request->boolean('clear_newsletter_token')) {
            $tenant->newsletter_token = null;
        } elseif (filled($data['newsletter_token'] ?? null)) {
            $tenant->newsletter_token = trim($data['newsletter_token']);
        }

        // WeFact: sleutel is write-only (leeg = behouden); btw-codes per tarief (leeg = standaard V21, V12, V6, V0).
        $keyChanged = false;
        if ($request->boolean('clear_wefact_api_key')) {
            $tenant->wefact_api_key = null;
        } elseif (filled($data['wefact_api_key'] ?? null)) {
            $tenant->wefact_api_key = trim($data['wefact_api_key']);
            $keyChanged = true;
        }
        $taxCodes = [];
        foreach (['21', '12', '6', '0'] as $rate) {
            if (filled($data['wefact_tax_' . $rate] ?? null)) {
                $taxCodes[$rate] = strtoupper(trim($data['wefact_tax_' . $rate]));
            }
        }
        $tenant->wefact_tax_codes = $taxCodes ?: null;
        $tenant->wefact_payment_term = filled($data['wefact_payment_term'] ?? null) ? (int) $data['wefact_payment_term'] : null;

        $tenant->save();

        $message = 'Tenantinstellingen werden opgeslagen.';
        $wefactError = null;
        if (($keyChanged || $request->boolean('test_wefact')) && filled($tenant->wefact_api_key)) {
            try {
                (new WeFactClient((string) $tenant->wefact_api_key))->call('debtor', 'list', ['limit' => 1]);
                $message .= ' Verbinding met WeFact werkt.';
            } catch (\Throwable $e) {
                $wefactError = 'Opgeslagen, maar WeFact antwoordt niet: ' . $e->getMessage()
                    . ' Staat het IP-adres van de server op de whitelist (WeFact → Instellingen → API)?';
            }
        }

        return response()->json([
            'message' => $message,
            'wefact_error' => $wefactError,
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
            'family_max_adults' => $tenant->family_max_adults,
            'family_max_children' => $tenant->family_max_children,
            'newsletter_url' => $tenant->newsletter_url,
            'newsletter_token_set' => filled($tenant->newsletter_token),
            'wefact_api_key_set' => filled($tenant->wefact_api_key),
            'wefact_tax_codes' => OrderInvoiceService::taxCodes($tenant),
            'wefact_payment_term' => OrderInvoiceService::paymentTerm($tenant),
        ];
    }
}
