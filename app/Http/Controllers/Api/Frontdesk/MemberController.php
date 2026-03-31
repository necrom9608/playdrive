<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontdesk\StoreMemberRequest;
use App\Http\Requests\Frontdesk\UpdateMemberRequest;
use App\Mail\MemberLifecycleMail;
use App\Models\Member;
use App\Models\Registration;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MemberController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');
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

        $this->applyStatusFilter($query, $status, $today, $soonDate);

        $members = $query
            ->orderBy('membership_ends_at', 'asc')
            ->orderBy('last_name', 'asc')
            ->orderBy('first_name', 'asc')
            ->get();

        $baseQuery = Member::query()
            ->where('tenant_id', $currentTenant->id());

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)
                ->where('is_active', true)
                ->whereDate('membership_ends_at', '>=', $today)
                ->count(),
            'expiring_soon' => (clone $baseQuery)
                ->where('is_active', true)
                ->whereDate('membership_ends_at', '>=', $today)
                ->whereDate('membership_ends_at', '<=', $soonDate)
                ->count(),
            'expired' => (clone $baseQuery)
                ->where(function (Builder $query) use ($today) {
                    $query->where('is_active', false)
                        ->orWhereDate('membership_ends_at', '<', $today);
                })
                ->count(),
        ];

        return response()->json([
            'data' => [
                'summary' => $summary,
                'members' => $members->map(fn (Member $member) => $this->mapMember($member))->values(),
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
            'is_active' => (bool) ($data['is_active'] ?? true),
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
            'is_active' => (bool) ($data['is_active'] ?? true),
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
        $member->is_active = true;
        $member->save();

        return response()->json([
            'data' => $this->mapMember($member->fresh()),
        ]);
    }


    public function toggleAttendance(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validate([
            'member_id' => ['nullable', 'integer'],
            'query' => ['nullable', 'string', 'max:255'],
        ]);

        $member = null;

        if (!empty($data['member_id'])) {
            $member = Member::query()
                ->where('tenant_id', $currentTenant->id())
                ->find($data['member_id']);
        }

        if (!$member && !empty($data['query'])) {
            $search = trim((string) $data['query']);

            $member = Member::query()
                ->where('tenant_id', $currentTenant->id())
                ->where(function (Builder $query) use ($search) {
                    $query
                        ->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ['%' . $search . '%'])
                        ->orWhere('login', 'like', '%' . $search . '%')
                        ->orWhere('rfid_uid', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->first();
        }

        if (!$member) {
            return response()->json([
                'message' => 'Geen lid gevonden.',
            ], 404);
        }

        $today = now()->startOfDay();
        $endsAt = $member->membership_ends_at?->copy()->startOfDay();

        if (!(bool) $member->is_active || ($endsAt && $endsAt->lt($today))) {
            return response()->json([
                'message' => 'Dit lid heeft geen actief abonnement.',
                'member' => $this->mapMember($member),
            ], 422);
        }

        $actorUserId = $request->attributes->get('frontdesk_user')?->id;

        $openRegistration = Registration::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('is_member', true)
            ->where('member_id', $member->id)
            ->where('status', Registration::STATUS_CHECKED_IN)
            ->latest('id')
            ->first();

        $registration = DB::transaction(function () use ($member, $currentTenant, $actorUserId, $openRegistration) {
            if ($openRegistration) {
                $openRegistration->status = Registration::STATUS_CHECKED_OUT;
                $openRegistration->checked_out_at = now();
                $openRegistration->checked_out_by = $actorUserId;
                $openRegistration->updated_by = $actorUserId;

                if ($openRegistration->checked_in_at) {
                    $openRegistration->played_minutes = max(
                        0,
                        $openRegistration->checked_in_at->diffInMinutes($openRegistration->checked_out_at)
                    );
                }

                $openRegistration->save();

                return $openRegistration->fresh();
            }

            [$children, $adults] = match ((string) $member->membership_type) {
                'child' => [1, 0],
                default => [0, 1],
            };

            return Registration::query()->create([
                'tenant_id' => $currentTenant->id(),
                'name' => trim($member->first_name . ' ' . $member->last_name),
                'phone' => null,
                'email' => $this->nullableValue($member->email),
                'postal_code' => $this->nullableValue($member->postal_code),
                'municipality' => $this->nullableValue($member->city),
                'event_type_id' => null,
                'event_date' => now()->toDateString(),
                'event_time' => now()->format('H:i'),
                'stay_option_id' => null,
                'catering_option_id' => null,
                'participants_children' => $children,
                'participants_adults' => $adults,
                'participants_supervisors' => 0,
                'comment' => 'Lidbezoek',
                'stats' => [],
                'status' => Registration::STATUS_CHECKED_IN,
                'invoice_requested' => false,
                'invoice_company_name' => null,
                'invoice_vat_number' => null,
                'invoice_email' => null,
                'invoice_address' => null,
                'invoice_postal_code' => null,
                'invoice_city' => null,
                'checked_in_at' => now(),
                'checked_in_by' => $actorUserId,
                'checked_out_at' => null,
                'checked_out_by' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'no_show_at' => null,
                'no_show_by' => null,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
                'played_minutes' => null,
                'bill_total_cents' => 0,
                'outside_opening_hours' => false,
                'is_member' => true,
                'member_id' => $member->id,
            ]);
        });

        return response()->json([
            'message' => $openRegistration ? 'Lid uitgecheckt.' : 'Lid ingecheckt.',
            'action' => $openRegistration ? 'checked_out' : 'checked_in',
            'member' => $this->mapMember($member->fresh()),
            'registration' => [
                'id' => $registration->id,
                'status' => $registration->status,
                'checked_in_at' => optional($registration->checked_in_at)?->toIso8601String(),
                'checked_out_at' => optional($registration->checked_out_at)?->toIso8601String(),
                'played_minutes' => $registration->played_minutes,
                'is_member' => (bool) $registration->is_member,
                'member_id' => $registration->member_id,
            ],
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

        $member->{$timestampField} = now();
        $member->save();

        return response()->json([
            'success' => true,
            'data' => $this->mapMember($member->fresh()),
        ]);
    }

    private function applyStatusFilter(Builder $query, string $status, Carbon $today, Carbon $soonDate): void
    {
        match ($status) {
            'active' => $query
                ->where('is_active', true)
                ->whereDate('membership_ends_at', '>=', $today),

            'expiring' => $query
                ->where('is_active', true)
                ->whereDate('membership_ends_at', '>=', $today)
                ->whereDate('membership_ends_at', '<=', $soonDate),

            'expired' => $query->where(function (Builder $subQuery) use ($today) {
                $subQuery->where('is_active', false)
                    ->orWhereDate('membership_ends_at', '<', $today);
            }),

            'inactive' => $query->where('is_active', false),

            default => null,
        };
    }

    private function mapMember(Member $member): array
    {
        $today = now()->startOfDay();
        $endsAt = $member->membership_ends_at?->copy()->startOfDay();
        $daysUntilExpiry = $endsAt ? (int) $today->diffInDays($endsAt, false) : null;

        $status = 'inactive';

        if ((bool) $member->is_active) {
            if ($endsAt && $endsAt->lt($today)) {
                $status = 'expired';
            } elseif ($daysUntilExpiry !== null && $daysUntilExpiry <= 30) {
                $status = 'expiring';
            } else {
                $status = 'active';
            }
        } elseif ($endsAt && $endsAt->lt($today)) {
            $status = 'expired';
        }

        return [
            'id' => $member->id,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'full_name' => trim($member->first_name . ' ' . $member->last_name),
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
            'is_active' => (bool) $member->is_active,
            'confirmation_mail_sent_at' => $member->confirmation_mail_sent_at?->format('Y-m-d H:i:s'),
            'expiry_warning_mail_sent_at' => $member->expiry_warning_mail_sent_at?->format('Y-m-d H:i:s'),
            'expired_mail_sent_at' => $member->expired_mail_sent_at?->format('Y-m-d H:i:s'),
        ];
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
}
