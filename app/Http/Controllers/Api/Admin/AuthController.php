<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        if (! $request->session()->get('playdrive_admin_auth', false)) {
            return response()->json(['user' => null], 401);
        }

        return response()->json([
            'user' => [
                'name' => 'Admin',
            ],
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $expectedUsername  = (string) env('PLAYDRIVE_ADMIN_USERNAME', 'admin');
        $expectedPassword  = (string) env('PLAYDRIVE_ADMIN_PASSWORD', 'change-me');
        $expectedPasswordHash = (string) env('PLAYDRIVE_ADMIN_PASSWORD_HASH', '');

        $validPassword = $expectedPasswordHash !== ''
            ? Hash::check($data['password'], $expectedPasswordHash)
            : hash_equals($expectedPassword, $data['password']);

        if (! hash_equals($expectedUsername, $data['username']) || ! $validPassword) {
            throw ValidationException::withMessages([
                'username' => ['Ongeldige admin-login of paswoord.'],
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('playdrive_admin_auth', true);

        return response()->json([
            'user' => [
                'name' => 'Admin',
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget('playdrive_admin_auth');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }
}
