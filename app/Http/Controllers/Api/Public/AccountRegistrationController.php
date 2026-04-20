<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Mail\AccountVerificationMail;
use App\Models\Account;
use App\Models\AccountEmailVerification;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

        $account = DB::transaction(function () use ($data, $tenant) {
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

            return $account;
        });

        // Stuur verificatiemail
        $this->sendVerificationMail($account, $tenant->slug, $tenant->display_name);

        return response()->json([
            'message' => 'Account succesvol aangemaakt. Controleer je e-mail om je account te bevestigen.',
        ], 201);
    }

    public function verify(string $token): \Illuminate\Http\RedirectResponse
    {
        $verification = AccountEmailVerification::query()
            ->where('token', $token)
            ->with('account')
            ->first();

        if (! $verification) {
            return redirect()->to(config('app.url') . '/client/verified?status=invalid');
        }

        if ($verification->isExpired()) {
            $verification->delete();
            return redirect()->to(config('app.url') . '/client/verified?status=expired');
        }

        $account    = $verification->account;
        $tenantSlug = $verification->tenant_slug;

        // Markeer e-mail als bevestigd
        $account->email_verified_at = now();
        $account->save();

        // Token verwijderen
        $verification->delete();

        $redirectUrl = config('app.url') . '/client/verified?status=success';
        if ($tenantSlug) {
            $redirectUrl .= '&tenant=' . $tenantSlug;
        }

        return redirect()->to($redirectUrl);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => ['required', 'email'],
            'tenant_slug' => ['nullable', 'string'],
        ]);

        $account = Account::query()
            ->where('email', strtolower(trim($data['email'])))
            ->first();

        // Altijd 200 teruggeven (privacy)
        if (! $account || $account->email_verified_at) {
            return response()->json(['message' => 'Als het adres bekend is, ontvang je een nieuwe e-mail.']);
        }

        $tenantSlug = $data['tenant_slug'] ?? null;
        $tenantName = null;

        if ($tenantSlug) {
            $tenant     = Tenant::query()->where('slug', $tenantSlug)->where('is_active', true)->first();
            $tenantName = $tenant?->display_name;
        }

        $this->sendVerificationMail($account, $tenantSlug, $tenantName);

        return response()->json(['message' => 'Als het adres bekend is, ontvang je een nieuwe e-mail.']);
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

    // -------------------------------------------------------------------------

    private function sendVerificationMail(Account $account, ?string $tenantSlug, ?string $tenantName): void
    {
        // Verwijder eventuele oude tokens voor dit account
        AccountEmailVerification::query()
            ->where('account_id', $account->id)
            ->delete();

        $token = Str::random(64);

        AccountEmailVerification::query()->create([
            'account_id'  => $account->id,
            'token'       => $token,
            'tenant_slug' => $tenantSlug,
            'expires_at'  => now()->addHours(24),
        ]);

        $verifyUrl = config('app.url') . '/api/register/verify/' . $token;

        Mail::to($account->email)->send(
            new AccountVerificationMail($account, $verifyUrl, $tenantName)
        );
    }
}
