<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function me(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $user = $this->currentUser($request, $currentTenant);

        return response()->json([
            'authenticated' => $user !== null,
            'user' => $user ? $this->mapUser($user) : null,
        ]);
    }

    public function login(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('is_active', true)
            ->where('username', $data['username'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Ongeldige login of paswoord.'],
            ]);
        }

        $this->storeUserInSession($request, $user);

        return response()->json([
            'authenticated' => true,
            'user' => $this->mapUser($user),
        ]);
    }

    public function loginWithCard(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validate([
            'rfid_uid' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('is_active', true)
            ->where('rfid_uid', $data['rfid_uid'])
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'rfid_uid' => ['Onbekende of niet-actieve NFC-kaart.'],
            ]);
        }

        $this->storeUserInSession($request, $user);

        return response()->json([
            'authenticated' => true,
            'user' => $this->mapUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget('frontdesk_auth');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
        ]);
    }

    protected function currentUser(Request $request, CurrentTenant $currentTenant): ?User
    {
        $auth = $request->session()->get('frontdesk_auth');

        if (! is_array($auth)) {
            return null;
        }

        $userId = $auth['user_id'] ?? null;
        $tenantId = $auth['tenant_id'] ?? null;

        if (! $userId || ! $tenantId || (int) $tenantId !== (int) $currentTenant->id()) {
            return null;
        }

        return User::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('is_active', true)
            ->find($userId);
    }

    protected function storeUserInSession(Request $request, User $user): void
    {
        $request->session()->regenerate();

        $request->session()->put('frontdesk_auth', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    protected function mapUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
        ];
    }
}
