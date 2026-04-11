<?php

namespace App\Http\Controllers\Api\Display;

use App\Events\DisplayStateUpdated;
use App\Http\Controllers\Controller;
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
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('members', 'email')->where(fn ($query) => $query->where('tenant_id', $currentTenant->id())),
            ],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'membership_type' => ['required', 'in:adult,student'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'badge_template_id' => ['nullable', 'integer'],
        ]);

        $display = DisplayDevice::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('device_uuid', $data['device_uuid'])
            ->firstOrFail();

        if (! hash_equals((string) $display->device_token, (string) $data['device_token'])) {
            abort(403, 'Ongeldig display token.');
        }

        $startDate = now()->startOfDay();
        $endDate = $startDate->copy()->addYear();

        $member = DB::transaction(function () use ($data, $currentTenant, $startDate, $endDate) {
            $memberPayload = [
                'tenant_id' => $currentTenant->id(),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'birth_date' => !empty($data['birth_date']) ? Carbon::parse($data['birth_date'])->toDateString() : null,
                'membership_type' => $data['membership_type'],
                'email' => Str::lower($data['email']),
                'phone' => $this->nullableValue($data['phone'] ?? null),
                'login' => Str::lower($data['email']),
                'password' => $data['password'],
                'street' => $this->nullableValue($data['street'] ?? null),
                'house_number' => $this->nullableValue($data['house_number'] ?? null),
                'postal_code' => $this->nullableValue($data['postal_code'] ?? null),
                'city' => $this->nullableValue($data['city'] ?? null),
                'country' => 'BE',
                'membership_starts_at' => $startDate->toDateString(),
                'membership_ends_at' => $endDate->toDateString(),
            ];

            if (Schema::hasColumn('members', 'is_active')) {
                $memberPayload['is_active'] = true;
            }

            /** @var Member $member */
            $member = Member::query()->create($memberPayload);

            $templateId = !empty($data['badge_template_id']) ? (int) $data['badge_template_id'] : $this->defaultMemberBadgeTemplateId($currentTenant->id());

            if ($templateId) {
                PhysicalCard::query()->create([
                    'tenant_id' => $currentTenant->id(),
                    'card_type' => PhysicalCard::TYPE_MEMBER,
                    'badge_template_id' => $templateId,
                    'holder_type' => PhysicalCard::TYPE_MEMBER,
                    'holder_id' => $member->id,
                    'label' => sprintf('MEMBER #%d - %s', $member->id, trim($member->first_name . ' ' . $member->last_name)),
                    'rfid_uid' => null,
                    'status' => PhysicalCard::STATUS_IN_CIRCULATION,
                    'issued_at' => now()->startOfDay(),
                ]);
            }

            return $member;
        });

        $successPayload = [
            'step' => 'success',
            'member' => [
                'id' => $member->id,
                'full_name' => trim($member->first_name . ' ' . $member->last_name),
                'email' => $member->email,
                'membership_starts_at' => $startDate->format('d/m/Y'),
                'membership_ends_at' => $endDate->format('d/m/Y'),
            ],
            'member_badge_templates' => $display->current_payload['member_badge_templates'] ?? [],
            'default_badge_template_id' => $display->current_payload['default_badge_template_id'] ?? null,
            'synced_at' => now()->toIso8601String(),
        ];

        $display->update([
            'current_mode' => 'member_registration',
            'current_payload' => $successPayload,
            'last_seen_at' => now(),
        ]);

        broadcast(new DisplayStateUpdated($display, $successPayload));

        return response()->json([
            'message' => 'Lid succesvol aangemaakt.',
            'data' => [
                'id' => $member->id,
                'full_name' => trim($member->first_name . ' ' . $member->last_name),
            ],
        ], 201);
    }

    private function defaultMemberBadgeTemplateId(int $tenantId): ?int
    {
        return DB::table('badge_templates')
            ->where('tenant_id', $tenantId)
            ->where('template_type', PhysicalCard::TYPE_MEMBER)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');
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
