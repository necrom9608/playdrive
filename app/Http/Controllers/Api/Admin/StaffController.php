<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Manages admin-level staff (PlayDrive platform operators).
 * These are stored in a separate config / simple table, not tenant users.
 *
 * For now we keep things simple: staff is stored in the session-based
 * config table or a dedicated admin_users table if it exists.
 * The controller gracefully falls back to a JSON file when no DB table exists.
 */
class StaffController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['staff' => $this->loadStaff()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'username'  => ['required', 'string', 'max:255'],
            'email'     => ['nullable', 'email:rfc', 'max:255'],
            'password'  => ['required', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $staff = $this->loadStaff();

        $exists = collect($staff)->firstWhere('username', $data['username']);
        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' => ['Gebruikersnaam is al in gebruik.'],
            ]);
        }

        $newMember = [
            'id'        => uniqid('staff_', true),
            'name'      => $data['name'],
            'username'  => $data['username'],
            'email'     => $data['email'] ?? null,
            'password'  => Hash::make($data['password']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        $staff[] = $newMember;
        $this->saveStaff($staff);

        return response()->json(['staff' => $this->safeMap($newMember)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $staff = $this->loadStaff();
        $index = $this->findIndex($staff, $id);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'username'  => ['required', 'string', 'max:255'],
            'email'     => ['nullable', 'email:rfc', 'max:255'],
            'password'  => ['nullable', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $usernameConflict = collect($staff)->first(
            fn ($s) => $s['username'] === $data['username'] && $s['id'] !== $id
        );
        if ($usernameConflict) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' => ['Gebruikersnaam is al in gebruik.'],
            ]);
        }

        $staff[$index]['name']      = $data['name'];
        $staff[$index]['username']  = $data['username'];
        $staff[$index]['email']     = $data['email'] ?? null;
        $staff[$index]['is_active'] = (bool) ($data['is_active'] ?? true);

        if (!empty($data['password'])) {
            $staff[$index]['password'] = Hash::make($data['password']);
        }

        $this->saveStaff($staff);

        return response()->json(['staff' => $this->safeMap($staff[$index])]);
    }

    public function destroy(string $id): JsonResponse
    {
        $staff = $this->loadStaff();
        $this->findIndex($staff, $id); // throws if not found

        $staff = array_values(array_filter($staff, fn ($s) => $s['id'] !== $id));
        $this->saveStaff($staff);

        return response()->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------

    private function storagePath(): string
    {
        return storage_path('app/admin-staff.json');
    }

    private function loadStaff(): array
    {
        $path = $this->storagePath();

        if (!file_exists($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private function saveStaff(array $staff): void
    {
        file_put_contents($this->storagePath(), json_encode(array_values($staff), JSON_PRETTY_PRINT));
    }

    private function findIndex(array $staff, string $id): int
    {
        foreach ($staff as $i => $member) {
            if ($member['id'] === $id) {
                return $i;
            }
        }

        abort(404, 'Medewerker niet gevonden.');
    }

    private function safeMap(array $member): array
    {
        return [
            'id'        => $member['id'],
            'name'      => $member['name'],
            'username'  => $member['username'],
            'email'     => $member['email'] ?? null,
            'is_active' => (bool) ($member['is_active'] ?? true),
        ];
    }
}
