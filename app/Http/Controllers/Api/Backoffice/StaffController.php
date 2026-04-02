<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\StoreStaffRequest;
use App\Http\Requests\Backoffice\UpdateStaffRequest;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        $staff = User::query()
            ->where('tenant_id', $currentTenant->id())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->mapStaff($user))
            ->values();

        return response()->json($staff);
    }

    public function store(StoreStaffRequest $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validated();

        $nextSortOrder = (int) User::query()
            ->where('tenant_id', $currentTenant->id())
            ->max('sort_order');

        $user = User::query()->create([
            'tenant_id' => $currentTenant->id(),
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $this->nullableValue($data['email'] ?? null),
            'password' => $data['password'],
            'rfid_uid' => $this->nullableValue($data['rfid_uid'] ?? null),
            'street' => $this->nullableValue($data['street'] ?? null),
            'house_number' => $this->nullableValue($data['house_number'] ?? null),
            'bus' => $this->nullableValue($data['bus'] ?? null),
            'postal_code' => $this->nullableValue($data['postal_code'] ?? null),
            'city' => $this->nullableValue($data['city'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'sort_order' => $nextSortOrder + 1,
        ]);

        return response()->json($this->mapStaff($user), 201);
    }

    public function update(UpdateStaffRequest $request, CurrentTenant $currentTenant, User $staff): JsonResponse
    {
        abort_unless((int) $staff->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validated();

        $staff->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $this->nullableValue($data['email'] ?? null),
            'rfid_uid' => $this->nullableValue($data['rfid_uid'] ?? null),
            'street' => $this->nullableValue($data['street'] ?? null),
            'house_number' => $this->nullableValue($data['house_number'] ?? null),
            'bus' => $this->nullableValue($data['bus'] ?? null),
            'postal_code' => $this->nullableValue($data['postal_code'] ?? null),
            'city' => $this->nullableValue($data['city'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_admin' => (bool) ($data['is_admin'] ?? false),
        ]);

        if (! empty($data['password'])) {
            $staff->password = $data['password'];
        }

        $staff->save();

        return response()->json($this->mapStaff($staff->fresh()));
    }

    public function destroy(CurrentTenant $currentTenant, User $staff): JsonResponse
    {
        abort_unless((int) $staff->tenant_id === (int) $currentTenant->id(), 404);

        $staff->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function reorder(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
        ]);

        $ids = collect($data['items'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $existingIds = User::query()
            ->where('tenant_id', $currentTenant->id())
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($existingIds->count() !== $ids->count()) {
            return response()->json([
                'message' => 'Ongeldige medewerkerlijst voor herschikken.',
            ], 422);
        }

        DB::transaction(function () use ($ids, $currentTenant) {
            foreach ($ids as $index => $id) {
                User::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('id', $id)
                    ->update([
                        'sort_order' => $index + 1,
                    ]);
            }
        });

        return response()->json([
            'success' => true,
        ]);
    }

    private function mapStaff(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'rfid_uid' => $user->rfid_uid,
            'street' => $user->street,
            'house_number' => $user->house_number,
            'bus' => $user->bus,
            'postal_code' => $user->postal_code,
            'city' => $user->city,
            'full_address' => $this->formatAddress($user),
            'is_active' => (bool) $user->is_active,
            'is_admin' => (bool) $user->is_admin,
            'sort_order' => (int) $user->sort_order,
        ];
    }

    private function formatAddress(User $user): ?string
    {
        $line1 = trim(implode(' ', array_filter([
            $user->street,
            $user->house_number,
        ])));

        if (! empty($user->bus)) {
            $line1 = trim($line1 . ' bus ' . $user->bus);
        }

        $line2 = trim(implode(' ', array_filter([
            $user->postal_code,
            $user->city,
        ])));

        $parts = array_values(array_filter([$line1, $line2]));

        return empty($parts) ? null : implode(', ', $parts);
    }

    private function nullableValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value;
    }
}
