<?php

namespace App\Http\Controllers\Api\Display;

use App\Http\Controllers\Controller;
use App\Models\BadgeTemplate;
use App\Models\DisplayDevice;
use App\Models\Member;
use App\Models\PhysicalCard;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MemberRegistrationController extends Controller
{
    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden voor deze host.');

        $data = $request->validate([
            'device_uuid' => ['required', 'uuid'],
            'device_token' => ['required', 'string'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('members', 'login')->where(fn ($query) => $query->where('tenant_id', $currentTenant->id()))],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'same:password_confirmation'],
            'password_confirmation' => ['required', 'string', 'min:6'],
            'birth_date' => ['nullable', 'date'],
            'membership_type' => ['nullable', 'in:adult,student'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'badge_template_id' => [
                'required',
                'integer',
                Rule::exists(BadgeTemplate::class, 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $currentTenant->id())
                    ->where('template_type', PhysicalCard::TYPE_MEMBER)),
            ],
        ]);

        $display = DisplayDevice::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('device_uuid', $data['device_uuid'])
            ->first();

        abort_unless($display, 404, 'Display niet gevonden.');

        if (! hash_equals((string) $display->device_token, (string) $data['device_token'])) {
            abort(403, 'Ongeldig display token.');
        }

        abort_unless($display->posDevices()->exists(), 422, 'Deze display is nog niet gekoppeld aan een POS-terminal.');

        $today = now()->startOfDay();
        $endsAt = $today->copy()->addYear();

        $member = DB::transaction(function () use ($data, $currentTenant, $today, $endsAt) {
            $memberPayload = [
                'tenant_id' => $currentTenant->id(),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'login' => $data['email'],
                'password' => $data['password'],
                'street' => $this->nullableValue($data['street'] ?? null),
                'house_number' => $this->nullableValue($data['house_number'] ?? null),
                'postal_code' => $this->nullableValue($data['postal_code'] ?? null),
                'city' => $this->nullableValue($data['city'] ?? null),
                'membership_starts_at' => $today->toDateString(),
                'membership_ends_at' => $endsAt->toDateString(),
            ];

            if ($this->membersHasPhoneColumn()) {
                $memberPayload['phone'] = $this->nullableValue($data['phone'] ?? null);
            }

            if ($this->membersHasBirthDateColumn()) {
                $memberPayload['birth_date'] = ! empty($data['birth_date'])
                    ? Carbon::parse($data['birth_date'])->toDateString()
                    : null;
            }

            if ($this->membersHasMembershipTypeColumn()) {
                $memberPayload['membership_type'] = $data['membership_type'] ?? 'adult';
            }

            if ($this->membersHasIsActiveColumn()) {
                $memberPayload['is_active'] = true;
            }

            $member = Member::query()->create($memberPayload);

            PhysicalCard::query()->create([
                'tenant_id' => $currentTenant->id(),
                'card_type' => PhysicalCard::TYPE_MEMBER,
                'badge_template_id' => (int) $data['badge_template_id'],
                'holder_type' => PhysicalCard::TYPE_MEMBER,
                'holder_id' => $member->id,
                'label' => sprintf('MEMBER #%d - %s', $member->id, trim($member->first_name . ' ' . $member->last_name)),
                'rfid_uid' => $this->temporaryRfidUid($currentTenant->id(), $member->id),
                'status' => PhysicalCard::STATUS_IN_CIRCULATION,
                'issued_at' => now()->startOfDay(),
            ]);

            return $member;
        });

        $display->update([
            'current_mode' => DisplayDevice::MODE_MEMBER_REGISTRATION,
            'current_payload' => [
                'member_registration' => [
                    'step' => 2,
                    'success' => true,
                    'success_message' => sprintf('%s %s werd toegevoegd.', $member->first_name, $member->last_name),
                ],
            ],
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'message' => 'Lid succesvol aangemaakt.',
            'data' => [
                'member' => [
                    'id' => $member->id,
                    'full_name' => trim($member->first_name . ' ' . $member->last_name),
                ],
            ],
        ], 201);
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

    private function temporaryRfidUid(int $tenantId, int $memberId): string
    {
        return sprintf('TMP-MEMBER-%d-%d-%s', $tenantId, $memberId, Str::upper(Str::random(8)));
    }

    private function membersHasBirthDateColumn(): bool
    {
        static $result = null;

        return $result ??= Schema::hasColumn('members', 'birth_date');
    }

    private function membersHasPhoneColumn(): bool
    {
        static $result = null;

        return $result ??= Schema::hasColumn('members', 'phone');
    }

    private function membersHasMembershipTypeColumn(): bool
    {
        static $result = null;

        return $result ??= Schema::hasColumn('members', 'membership_type');
    }

    private function membersHasIsActiveColumn(): bool
    {
        static $result = null;

        return $result ??= Schema::hasColumn('members', 'is_active');
    }
}
