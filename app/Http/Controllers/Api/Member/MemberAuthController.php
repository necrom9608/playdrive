<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class MemberAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name'            => ['required', 'string', 'max:255'],
            'last_name'             => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:accounts,email'],
            'password'              => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ], [
            'email.unique'          => 'Dit e-mailadres is al geregistreerd.',
            'password.confirmed'    => 'De wachtwoorden komen niet overeen.',
        ]);

        $account = Account::query()->create([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => strtolower(trim($data['email'])),
            'password'   => $data['password'],
        ]);

        // Verificatiemail via eigen systeem (ondersteunt Resend + aanpasbare templates)
        try {
            $this->sendVerificationMail($account);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Verificatiemail (member register) mislukt', [
                'account_id' => $account->id,
                'error'      => $e->getMessage(),
            ]);
        }

        $token = $account->createToken(
            $request->input('device_name', 'mobile')
        )->plainTextToken;

        return response()->json([
            'token'   => $token,
            'account' => $this->accountData($account),
        ], 201);
    }

    private function sendVerificationMail(Account $account): void
    {
        \App\Models\AccountEmailVerification::query()
            ->where('account_id', $account->id)
            ->delete();

        $token = \Illuminate\Support\Str::random(64);

        \App\Models\AccountEmailVerification::query()->create([
            'account_id'  => $account->id,
            'token'       => $token,
            'tenant_slug' => null,
            'expires_at'  => now()->addHours(24),
        ]);

        $verifyUrl = config('app.url') . '/api/register/verify/' . $token;

        \Illuminate\Support\Facades\Mail::to($account->email)->send(
            new \App\Mail\AccountVerificationMail($account, $verifyUrl, null)
        );
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $account = Account::query()
            ->where('email', strtolower(trim($data['email'])))
            ->first();

        if (! $account || ! Hash::check($data['password'], $account->password)) {
            return response()->json([
                'message' => 'Deze combinatie van e-mail en wachtwoord is niet correct.',
                'errors'  => [
                    'email' => ['Deze combinatie van e-mail en wachtwoord is niet correct.'],
                ],
            ], 422);
        }

        $token = $account->createToken(
            $request->input('device_name', 'mobile')
        )->plainTextToken;

        return response()->json([
            'token'   => $token,
            'account' => $this->accountData($account),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Succesvol uitgelogd.']);
    }

    public function me(Request $request): JsonResponse
    {
        $account = $request->user()->load('memberships.tenant');

        return response()->json([
            'data' => array_merge(
                $this->accountData($account),
                [
                    'memberships' => $account->memberships->map(fn ($m) => [
                        'tenant_slug'       => $m->tenant?->slug,
                        'tenant_name'       => $m->tenant?->display_name,
                        'is_active'         => $m->is_active,
                        'membership_type'   => $m->membership_type,
                    ]),
                ]
            ),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('accounts')->sendResetLink(
            ['email' => strtolower(trim($request->email))]
        );

        return response()->json([
            'message' => 'Als dit e-mailadres gekend is, ontvang je een resetlink.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'                 => ['required'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::broker('accounts')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Account $account, string $password) {
                $account->forceFill(['password' => $password])
                    ->setRememberToken(Str::random(60));
                $account->save();
                event(new PasswordReset($account));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
                'errors'  => ['email' => [__($status)]],
            ], 422);
        }

        return response()->json(['message' => 'Wachtwoord succesvol gewijzigd.']);
    }

    // ------------------------------------------------------------------

    private function accountData(Account $account): array
    {
        return [
            'id'                => $account->id,
            'first_name'        => $account->first_name,
            'last_name'         => $account->last_name,
            'email'             => $account->email,
            'email_verified'    => $account->hasVerifiedEmail(),
            'phone'             => $account->phone ?? null,
            'birth_date'        => $account->birth_date?->toDateString(),
            'street'            => $account->street ?? null,
            'house_number'      => $account->house_number ?? null,
            'postal_code'       => $account->postal_code ?? null,
            'city'              => $account->city ?? null,
            'country'           => $account->country ?? null,
        ];
    }
}
