<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontdesk\StoreMemberRequest;
use App\Http\Requests\Frontdesk\UpdateMemberRequest;
use App\Mail\MemberLifecycleMail;
use App\Models\Member;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MemberController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $selectedStatuses = $this->normalizeStatuses($request->input('selected_statuses', []));
        $days = max(1, (int) $request->input('expiring_within_days', 30));

        $today = now()->startOfDay();
        $soonDate = now()->copy()->startOfDay()->addDays($days);

        $query = Member::query()
            ->where('tenant_id', $currentTenant->id());

        if ($search !== '') {
            $query->where(function (Builder $subQuery) use ($search) {
                $subQuery
                    ->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ['%' . $search . '%'])
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('login', 'like', '%' . $search . '%')
                    ->orWhere('rfid_uid', 'like', '%' . $search . '%');
            });
        }

        $this->applyStatusesFilter($query, $selectedStatuses, $today, $soonDate);

        $members = $query
            ->orderByRaw('membership_ends_at is null')
            ->orderBy('membership_ends_at', 'asc')
            ->orderBy('last_name', 'asc')
            ->orderBy('first_name', 'asc')
            ->get();

        $baseQuery = Member::query()
            ->where('tenant_id', $currentTenant->id());

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)
                ->whereDate('membership_ends_at', '>', $soonDate)
                ->count(),
            'expiring_soon' => (clone $baseQuery)
                ->whereDate('membership_ends_at', '>=', $today)
                ->whereDate('membership_ends_at', '<=', $soonDate)
                ->count(),
            'expired' => (clone $baseQuery)
                ->where(function (Builder $query) use ($today) {
                    $query
                        ->whereNull('membership_ends_at')
                        ->orWhereDate('membership_ends_at', '<', $today);
                })
                ->count(),
        ];

        return response()->json([
            'data' => [
                'summary' => $summary,
                'members' => $members->map(fn (Member $member) => $this->mapMember($member, $today, $soonDate))->values(),
            ],
        ]);
    }

    public function store(StoreMemberRequest $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validated();

        $startDate = !empty($data['membership_starts_at'])
            ? Carbon::parse($data['membership_starts_at'])->startOfDay()
            : now()->startOfDay();

        $endsDate = !empty($data['membership_ends_at'])
            ? Carbon::parse($data['membership_ends_at'])->startOfDay()
            : $startDate->copy()->addYear();

        $member = Member::query()->create([
            'tenant_id' => $currentTenant->id(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'membership_type' => $this->nullableValue($data['membership_type'] ?? 'adult'),
            'street' => $this->nullableValue($data['street'] ?? null),
            'house_number' => $this->nullableValue($data['house_number'] ?? null),
            'box' => $this->nullableValue($data['box'] ?? null),
            'postal_code' => $this->nullableValue($data['postal_code'] ?? null),
            'city' => $this->nullableValue($data['city'] ?? null),
            'country' => $this->nullableValue($data['country'] ?? null),
            'email' => $this->nullableValue($data['email'] ?? null),
            'login' => $this->nullableValue($data['login'] ?? null),
            'password' => $this->nullableValue($data['password'] ?? null),
            'rfid_uid' => $this->nullableValue($data['rfid_uid'] ?? null),
            'comment' => $this->nullableValue($data['comment'] ?? null),
            'membership_starts_at' => $startDate->toDateString(),
            'membership_ends_at' => $endsDate->toDateString(),
        ]);

        return response()->json([
            'data' => $this->mapMember($member->fresh()),
        ], 201);
    }

    public function update(UpdateMemberRequest $request, CurrentTenant $currentTenant, Member $member): JsonResponse
    {
        abort_unless((int) $member->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validated();

        $startDate = !empty($data['membership_starts_at'])
            ? Carbon::parse($data['membership_starts_at'])->startOfDay()
            : ($member->membership_starts_at?->copy()->startOfDay() ?? now()->startOfDay());

        $endsDate = !empty($data['membership_ends_at'])
            ? Carbon::parse($data['membership_ends_at'])->startOfDay()
            : ($member->membership_ends_at?->copy()->startOfDay() ?? $startDate->copy()->addYear());

        $member->fill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'membership_type' => $this->nullableValue($data['membership_type'] ?? 'adult'),
            'street' => $this->nullableValue($data['street'] ?? null),
            'house_number' => $this->nullableValue($data['house_number'] ?? null),
            'box' => $this->nullableValue($data['box'] ?? null),
            'postal_code' => $this->nullableValue($data['postal_code'] ?? null),
            'city' => $this->nullableValue($data['city'] ?? null),
            'country' => $this->nullableValue($data['country'] ?? null),
            'email' => $this->nullableValue($data['email'] ?? null),
            'login' => $this->nullableValue($data['login'] ?? null),
            'rfid_uid' => $this->nullableValue($data['rfid_uid'] ?? null),
            'comment' => $this->nullableValue($data['comment'] ?? null),
            'membership_starts_at' => $startDate->toDateString(),
            'membership_ends_at' => $endsDate->toDateString(),
        ]);

        if (!empty($data['password'])) {
            $member->password = $data['password'];
        }

        $member->save();

        return response()->json([
            'data' => $this->mapMember($member->fresh()),
        ]);
    }

    public function renew(CurrentTenant $currentTenant, Member $member): JsonResponse
    {
        abort_unless((int) $member->tenant_id === (int) $currentTenant->id(), 404);

        $today = now()->startOfDay();

        $newStart = $member->membership_ends_at && $member->membership_ends_at->greaterThanOrEqualTo($today)
            ? $member->membership_ends_at->copy()->addDay()
            : $today->copy();

        $member->membership_starts_at = $newStart->toDateString();
        $member->membership_ends_at = $newStart->copy()->addYear()->toDateString();
        $member->save();

        return response()->json([
            'data' => $this->mapMember($member->fresh()),
        ]);
    }

    public function sendEmail(Request $request, CurrentTenant $currentTenant, Member $member): JsonResponse
    {
        abort_unless((int) $member->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validate([
            'type' => ['required', 'in:confirmation,expiring,expired'],
        ]);

        if (empty($member->email)) {
            return response()->json([
                'message' => 'Deze abonnee heeft geen e-mailadres.',
            ], 422);
        }

        Mail::to($member->email)->send(new MemberLifecycleMail($member, $data['type']));

        $timestampField = match ($data['type']) {
            'confirmation' => 'confirmation_mail_sent_at',
            'expiring' => 'expiry_warning_mail_sent_at',
            'expired' => 'expired_mail_sent_at',
        };

        if ($this->memberHasAttribute($member, $timestampField)) {
            $member->{$timestampField} = now();
            $member->save();
        }

        return response()->json([
            'success' => true,
            'data' => $this->mapMember($member->fresh()),
        ]);
    }

    private function applyStatusesFilter(Builder $query, array $selectedStatuses, Carbon $today, Carbon $soonDate): void
    {
        if (empty($selectedStatuses)) {
            return;
        }

        $query->where(function (Builder $statusQuery) use ($selectedStatuses, $today, $soonDate) {
            foreach ($selectedStatuses as $status) {
                $statusQuery->orWhere(function (Builder $subQuery) use ($status, $today, $soonDate) {
                    match ($status) {
                        'active' => $subQuery->whereDate('membership_ends_at', '>', $soonDate),

                        'expiring' => $subQuery
                            ->whereDate('membership_ends_at', '>=', $today)
                            ->whereDate('membership_ends_at', '<=', $soonDate),

                        'expired' => $subQuery->where(function (Builder $expiredQuery) use ($today) {
                            $expiredQuery
                                ->whereNull('membership_ends_at')
                                ->orWhereDate('membership_ends_at', '<', $today);
                        }),

                        default => $subQuery,
                    };
                });
            }
        });
    }

    private function mapMember(Member $member, ?Carbon $today = null, ?Carbon $soonDate = null): array
    {
        $today = $today?->copy() ?? now()->startOfDay();
        $soonDate = $soonDate?->copy() ?? now()->startOfDay()->addDays(30);

        $endsAt = $member->membership_ends_at?->copy()->startOfDay();
        $daysUntilExpiry = $endsAt ? (int) $today->diffInDays($endsAt, false) : null;

        $status = 'expired';

        if ($endsAt) {
            if ($endsAt->lt($today)) {
                $status = 'expired';
            } elseif ($endsAt->lte($soonDate)) {
                $status = 'expiring';
            } else {
                $status = 'active';
            }
        }

        return [
            'id' => $member->id,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'full_name' => trim($member->first_name . ' ' . $member->last_name),
            'membership_type' => $member->membership_type,
            'membership_type_label' => $this->membershipTypeLabel($member->membership_type),
            'login' => $member->login,
            'email' => $member->email,
            'street' => $member->street,
            'house_number' => $member->house_number,
            'box' => $member->box,
            'postal_code' => $member->postal_code,
            'city' => $member->city,
            'country' => $member->country,
            'full_address' => $this->formatAddress($member),
            'rfid_uid' => $member->rfid_uid,
            'comment' => $member->comment,
            'membership_starts_at' => optional($member->membership_starts_at)->format('Y-m-d'),
            'membership_ends_at' => optional($member->membership_ends_at)->format('Y-m-d'),
            'membership_started_label' => optional($member->membership_starts_at)->format('d/m/Y'),
            'membership_expires_label' => optional($member->membership_ends_at)->format('d/m/Y'),
            'days_until_expiry' => $daysUntilExpiry,
            'status' => $status,
            'confirmation_mail_sent_at' => $this->memberHasAttribute($member, 'confirmation_mail_sent_at')
                ? $member->confirmation_mail_sent_at?->format('Y-m-d H:i:s')
                : null,
            'expiry_warning_mail_sent_at' => $this->memberHasAttribute($member, 'expiry_warning_mail_sent_at')
                ? $member->expiry_warning_mail_sent_at?->format('Y-m-d H:i:s')
                : null,
            'expired_mail_sent_at' => $this->memberHasAttribute($member, 'expired_mail_sent_at')
                ? $member->expired_mail_sent_at?->format('Y-m-d H:i:s')
                : null,
        ];
    }

    private function membershipTypeLabel(?string $value): string
    {
        return match ($value) {
            'child' => 'Kind',
            'adult' => 'Volwassen',
            'family' => 'Familie',
            default => 'Onbekend',
        };
    }

    private function normalizeStatuses(mixed $value): array
    {
        $statuses = is_array($value) ? $value : [$value];

        return collect($statuses)
            ->filter(fn ($status) => in_array($status, ['active', 'expiring', 'expired'], true))
            ->values()
            ->all();
    }

    private function formatAddress(Member $member): ?string
    {
        $line1 = trim(implode(' ', array_filter([
            $member->street,
            $member->house_number,
        ])));

        if (!empty($member->box)) {
            $line1 = trim($line1 . ' box ' . $member->box);
        }

        $line2 = trim(implode(' ', array_filter([
            $member->postal_code,
            $member->city,
        ])));

        $parts = array_values(array_filter([
            $line1,
            $line2,
            $member->country,
        ]));

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

    private function memberHasAttribute(Member $member, string $attribute): bool
    {
        return array_key_exists($attribute, $member->getAttributes())
            || in_array($attribute, $member->getFillable(), true);
    }
}
