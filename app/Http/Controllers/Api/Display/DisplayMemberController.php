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
use Illuminate\Validation\Rule;

class DisplayMemberController extends Controller
{
    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenantId = $currentTenant->id();

        $data = $request->validate([
            'device_uuid' => ['required', 'uuid'],
            'device_token' => ['required', 'string'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:6'],
            'password_confirmation' => ['required', 'same:password'],
            'birth_date' => ['nullable', 'date'],
            'membership_type' => ['required', Rule::in(['adult', 'student'])],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'badge_template_id' => [
                'nullable',
                'integer',
                Rule::exists(BadgeTemplate::class, 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('template_type', PhysicalCard::TYPE_MEMBER)),
            ],
        ]);

        $deviceQuery = DisplayDevice::query()
            ->where('device_uuid', $data['device_uuid'])
            ->where('device_token', $data['device_token']);

        if ($currentTenant->exists()) {
            $deviceQuery->where('tenant_id', $tenantId);
        }

        $displayDevice = $deviceQuery->firstOrFail();
        $displayDevice->forceFill(['last_seen_at' => now()])->save();

        $startDate = now()->startOfDay();
        $endDate = $startDate->copy()->addYear();

        $member = DB::transaction(function () use ($data, $tenantId, $startDate, $endDate) {
            $payload = [
                'tenant_id' => $tenantId,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'login' => $data['email'],
                'password' => $data['password'],
                'street' => $this->nullableValue($data['street'] ?? null),
                'house_number' => $this->nullableValue($data['house_number'] ?? null),
                'postal_code' => $this->nullableValue($data['postal_code'] ?? null),
                'city' => $this->nullableValue($data['city'] ?? null),
                'membership_starts_at' => $startDate->toDateString(),
                'membership_ends_at' => $endDate->toDateString(),
                'is_active' => true,
            ];

            if (Schema::hasColumn('members', 'membership_type')) {
                $payload['membership_type'] = $data['membership_type'];
            }

            if (Schema::hasColumn('members', 'birth_date')) {
                $payload['birth_date'] = $this->nullableValue($data['birth_date'] ?? null);
            }

            if (Schema::hasColumn('members', 'phone')) {
                $payload['phone'] = $this->nullableValue($data['phone'] ?? null);
            } else {
                $phone = trim((string) ($data['phone'] ?? ''));
                if ($phone !== '') {
                    $payload['comment'] = trim('Telefoon: ' . $phone);
                }
            }

            $member = Member::query()->create($payload);

            $this->syncMemberCard($member, $tenantId, $data['badge_template_id'] ?? null);

            return $member->fresh();
        });

        $displayDevice->forceFill([
            'current_mode' => DisplayDevice::MODE_STANDBY,
            'current_payload' => [
                'member_created' => true,
                'member' => [
                    'id' => $member->id,
                    'full_name' => trim($member->first_name . ' ' . $member->last_name),
                ],
            ],
            'last_synced_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Lid succesvol aangemaakt.',
            'data' => [
                'id' => $member->id,
                'full_name' => trim($member->first_name . ' ' . $member->last_name),
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

    private function syncMemberCard(Member $member, int $tenantId, mixed $badgeTemplateId = null): PhysicalCard
    {
        $resolvedBadgeTemplateId = $badgeTemplateId !== null && $badgeTemplateId !== ''
            ? (int) $badgeTemplateId
            : $this->defaultMemberBadgeTemplateId($tenantId);

        return PhysicalCard::query()->create([
            'tenant_id' => $tenantId,
            'card_type' => PhysicalCard::TYPE_MEMBER,
            'badge_template_id' => $resolvedBadgeTemplateId,
            'holder_type' => PhysicalCard::TYPE_MEMBER,
            'holder_id' => $member->id,
            'label' => sprintf('MEMBER #%d - %s', $member->id, trim($member->first_name . ' ' . $member->last_name)),
            'status' => PhysicalCard::STATUS_IN_CIRCULATION,
            'issued_at' => Carbon::now()->startOfDay(),
        ]);
    }

    private function defaultMemberBadgeTemplateId(int $tenantId): ?int
    {
        return BadgeTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('template_type', PhysicalCard::TYPE_MEMBER)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');
    }
}
