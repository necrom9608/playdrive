<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * v1.1 - Tenant wordt niet langer via subdomein bepaald bij login.
 * De user wordt opgezocht op username (platform-breed), de tenant volgt uit de user.
 */
class AuthController extends Controller
{
    public function me(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $user = $this->currentUser($request);

        return response()->json([
            'authenticated' => $user !== null,
            'user' => $user ? $this->mapUser($user) : null,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Zoek user platform-breed op username (geen tenant_id filter via subdomein).
        $user = User::query()
            ->where('is_active', true)
            ->where('is_admin', true)
            ->where('username', $data['username'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Ongeldige admin-login of paswoord.'],
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('backoffice_auth', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        return response()->json([
            'authenticated' => true,
            'user' => $this->mapUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget('backoffice_auth');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    private function currentUser(Request $request): ?User
    {
        $auth = $request->session()->get('backoffice_auth');

        if (! is_array($auth)) {
            return null;
        }

        $userId = $auth['user_id'] ?? null;
        $tenantId = $auth['tenant_id'] ?? null;

        if (! $userId || ! $tenantId) {
            return null;
        }

        return User::query()
            ->where('tenant_id', (int) $tenantId)
            ->where('is_active', true)
            ->where('is_admin', true)
            ->find($userId);
    }

    private function mapUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'is_admin' => (bool) $user->is_admin,
        ];
    }
}
