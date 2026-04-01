<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

    public function forgotPassword(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('is_active', true)
            ->where('username', $data['username'])
            ->first();

        if (! $user || ! $user->email) {
            throw ValidationException::withMessages([
                'username' => ['Geen actieve medewerker met e-mailadres gevonden voor deze login.'],
            ]);
        }

        $temporaryPassword = Str::random(10);
        $user->password = Hash::make($temporaryPassword);
        $user->save();

        Mail::raw(
            "Hallo {$user->name},\n\nJe tijdelijke paswoord voor de staffmodule van PlayDrive is:\n\n{$temporaryPassword}\n\nLog in met dit tijdelijke paswoord en wijzig daarna je paswoord in de instellingen.\n\nMet vriendelijke groet\nPlayDrive",
            function ($message) use ($user, $currentTenant) {
                $message
                    ->to($user->email, $user->name)
                    ->subject('Tijdelijk paswoord staffmodule' . ($currentTenant->tenant?->name ? ' - ' . $currentTenant->tenant->name : ''));
            }
        );

        return response()->json([
            'success' => true,
            'message' => 'Er werd een tijdelijk paswoord verzonden naar het e-mailadres van deze medewerker.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget('staff_auth');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    protected function currentUser(Request $request, CurrentTenant $currentTenant): ?User
    {
        $auth = $request->session()->get('staff_auth');

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
        $request->session()->put('staff_auth', [
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
            'email' => $user->email,
            'phone' => null,
            'address' => trim(implode(', ', array_filter([
                trim(implode(' ', array_filter([$user->street, $user->house_number]))),
                trim(implode(' ', array_filter([$user->postal_code, $user->city]))),
            ]))) ?: null,
        ];
    }
}
