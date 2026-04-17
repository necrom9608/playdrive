<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AccountRegistrationController extends Controller
{
    public function store(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::query()
            ->where('slug', $tenantSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:accounts,email'],
            'password'   => ['required', 'string', 'confirmed', Password::min(8)],
        ], [
            'email.unique'             => 'Dit e-mailadres is al geregistreerd.',
            'password.confirmed'       => 'De wachtwoorden komen niet overeen.',
            'password.min'             => 'Het wachtwoord moet minimaal 8 tekens bevatten.',
        ]);

        DB::transaction(function () use ($data, $tenant) {
            $account = Account::query()->create([
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => strtolower(trim($data['email'])),
                'password'   => $data['password'],
            ]);

            // Koppel aan tenant — geen actief abonnement, enkel de link
            TenantMembership::query()->create([
                'account_id' => $account->id,
                'tenant_id'  => $tenant->id,
                'is_active'  => false,
            ]);
        });

        return response()->json([
            'message' => 'Account succesvol aangemaakt.',
        ], 201);
    }

    public function tenantInfo(string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::query()
            ->where('slug', $tenantSlug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'name'     => $tenant->display_name,
                'slug'     => $tenant->slug,
                'logo_url' => $tenant->logo_url,
            ],
        ]);
    }
}
