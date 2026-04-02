<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (session('playdrive_admin_auth')) {
            return redirect()->route('admin.tenants.index');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $expectedUsername = (string) env('PLAYDRIVE_ADMIN_USERNAME', 'admin');
        $expectedPassword = (string) env('PLAYDRIVE_ADMIN_PASSWORD', 'change-me');
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

        return redirect()->route('admin.tenants.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('playdrive_admin_auth');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
