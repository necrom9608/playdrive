<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\StoreStaffRequest;
use App\Http\Requests\Backoffice\UpdateStaffRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function index(): JsonResponse
    {
        $staff = User::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $user->full_address = trim(collect([
                    trim(($user->street ?? '') . ' ' . ($user->house_number ?? '')),
                    $user->bus ? 'bus ' . $user->bus : null,
                    trim(($user->postal_code ?? '') . ' ' . ($user->city ?? '')),
                ])->filter()->implode(', '));

                return $user;
            });

        return response()->json($staff);
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $data = $request->validated();

        $nextSortOrder = (User::max('sort_order') ?? 0) + 1;

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'street' => $data['street'] ?? null,
            'house_number' => $data['house_number'] ?? null,
            'bus' => $data['bus'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'city' => $data['city'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $nextSortOrder,
        ]);

        return response()->json($user, 201);
    }

    public function update(UpdateStaffRequest $request, User $staff): JsonResponse
    {
        $data = $request->validated();

        $updateData = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'street' => $data['street'] ?? null,
            'house_number' => $data['house_number'] ?? null,
            'bus' => $data['bus'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'city' => $data['city'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $staff->update($updateData);

        return response()->json($staff);
    }

    public function destroy(User $staff): JsonResponse
    {
        $staff->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $items = $request->input('items', []);

        foreach ($items as $index => $item) {
            User::where('id', $item['id'])->update([
                'sort_order' => $index + 1,
            ]);
        }

        return response()->json(['success' => true]);
    }
}
