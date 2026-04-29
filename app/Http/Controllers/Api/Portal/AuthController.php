<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Portal authenticatie.
 *
 * Identiek patroon als Backoffice AuthController, maar met eigen sessie-key
 * 'portal_auth' zodat sessies elkaar niet beïnvloeden.
 */
class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $tenant = $user ? Tenant::find($user->tenant_id) : null;

        return response()->json([
            'authenticated' => $user !== null,
            'user' => $user ? $this->mapUser($user) : null,
            'tenant' => $tenant ? $this->mapTenant($tenant) : null,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('is_active', true)
            ->where('username', $data['username'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Ongeldige gebruikersnaam of paswoord.'],
            ]);
        }

        // Toegangscheck: dezelfde regel als de middleware
        if (! $user->is_admin && ! in_array($user->role, ['admin', 'manager'], true)) {
            throw ValidationException::withMessages([
                'username' => ['Geen toegang tot het portal.'],
            ]);
        }

        $tenant = Tenant::find($user->tenant_id);

        if (! $tenant) {
            throw ValidationException::withMessages([
                'username' => ['Geen geldige venue gekoppeld aan deze account.'],
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('portal_auth', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        return response()->json([
            'authenticated' => true,
            'user' => $this->mapUser($user),
            'tenant' => $this->mapTenant($tenant),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget('portal_auth');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    private function currentUser(Request $request): ?User
    {
        $auth = $request->session()->get('portal_auth');

        if (! is_array($auth)) {
            return null;
        }

        $userId = $auth['user_id'] ?? null;
        $tenantId = $auth['tenant_id'] ?? null;

        if (! $userId || ! $tenantId) {
            return null;
        }

        $user = User::query()
            ->where('tenant_id', (int) $tenantId)
            ->where('is_active', true)
            ->find($userId);

        if (! $user) {
            return null;
        }

        if (! $user->is_admin && ! in_array($user->role, ['admin', 'manager'], true)) {
            return null;
        }

        return $user;
    }

    private function mapUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'is_admin' => (bool) $user->is_admin,
        ];
    }

    private function mapTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'display_name' => $tenant->display_name,
            'public_status' => $tenant->public_status,
            'public_slug' => $tenant->public_slug,
            'subscription_tier' => $tenant->subscription_tier,
            'logo_url' => $tenant->logo_url,
        ];
    }
}
