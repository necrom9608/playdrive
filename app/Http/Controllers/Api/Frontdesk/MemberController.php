<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontdesk\StoreMemberRequest;
use App\Http\Requests\Frontdesk\UpdateMemberRequest;
use App\Mail\MemberLifecycleMail;
use App\Models\Member;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MemberController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->input('status', 'all');
        $days = max(1, (int) $request->input('expiring_within_days', 30));
        $today = now()->startOfDay();
        $soonDate = now()->startOfDay()->addDays($days);

        $query = Member::query()
            ->where('tenant_id', $currentTenant->id());

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ['%' . $search . '%'])
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('username', 'like', '%' . $search . '%')
                    ->orWhere('rfid_uid', 'like', '%' . $search . '%');
            });
        }

        $query = $this->applyStatusFilter($query, $status, $today, $soonDate);

        $members = $query
            ->orderBy('membership_expires_at')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $baseQuery = Member::query()->where('tenant_id', $currentTenant->id());

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)
                ->where('is_active', true)
                ->whereDate('membership_expires_at', '>=', $today)
                ->count(),
            'expiring_soon' => (clone $baseQuery)
                ->where('is_active', true)
                ->whereDate('membership_expires_at', '>=', $today)
                ->whereDate('membership_expires_at', '<=', $soonDate)
                ->count(),
            'expired' => (clone $baseQuery)
                ->where(function ($query) use ($today) {
                    $query->where('is_active', false)
                        ->orWhereDate('membership_expires_at', '<', $today);
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
        $startDate = !empty($data['membership_started_at']) ? Carbon::parse($data['membership_started_at']) : now()->startOfDay();
        $expiresDate = !empty($data['membership_expires_at']) ? Carbon::parse($data['membership_expires_at']) : $startDate->copy()->addYear();

        $nextSortOrder = (int) Member::query()
            ->where('tenant_id', $currentTenant->id())
            ->max('sort_order');

        $member = Member::query()->create([
            'tenant_id' => $currentTenant->id(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $this->nullableValue($data['username'] ?? null),
            'email' => $this->nullableValue($data['email'] ?? null),
            'password' => $this->nullableValue($data['password'] ?? null),
            'street' => $this->nullableValue($data['street'] ?? null),
            'house_number' => $this->nullableValue($data['house_number'] ?? null),
            'bus' => $this->nullableValue($data['bus'] ?? null),
            'postal_code' => $this->nullableValue($data['postal_code'] ?? null),
            'city' => $this->nullableValue($data['city'] ?? null),
            'rfid_uid' => $this->nullableValue($data['rfid_uid'] ?? null),
            'comment' => $this->nullableValue($data['comment'] ?? null),
            'membership_started_at' => $startDate->toDateString(),
            'membership_expires_at' => $expiresDate->toDateString(),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $nextSortOrder + 1,
        ]);

        return response()->json([
            'data' => $this->mapMember($member),
        ], 201);
    }

    public function update(UpdateMemberRequest $request, CurrentTenant $currentTenant, Member $member): JsonResponse
    {
        abort_unless((int) $member->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validated();
        $startDate = !empty($data['membership_started_at']) ? Carbon::parse($data['membership_started_at']) : $member->membership_started_at?->copy() ?? now()->startOfDay();
        $expiresDate = !empty($data['membership_expires_at']) ? Carbon::parse($data['membership_expires_at']) : $member->membership_expires_at?->copy() ?? $startDate->copy()->addYear();

        $member->fill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $this->nullableValue($data['username'] ?? null),
            'email' => $this->nullableValue($data['email'] ?? null),
            'street' => $this->nullableValue($data['street'] ?? null),
            'house_number' => $this->nullableValue($data['house_number'] ?? null),
            'bus' => $this->nullableValue($data['bus'] ?? null),
            'postal_code' => $this->nullableValue($data['postal_code'] ?? null),
            'city' => $this->nullableValue($data['city'] ?? null),
            'rfid_uid' => $this->nullableValue($data['rfid_uid'] ?? null),
            'comment' => $this->nullableValue($data['comment'] ?? null),
            'membership_started_at' => $startDate->toDateString(),
            'membership_expires_at' => $expiresDate->toDateString(),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if (! empty($data['password'])) {
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
        $newStart = $member->membership_expires_at && $member->membership_expires_at->greaterThanOrEqualTo($today)
            ? $member->membership_expires_at->copy()->addDay()
            : $today;

        $member->membership_started_at = $newStart;
        $member->membership_expires_at = $newStart->copy()->addYear();
        $member->is_active = true;
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

        $member->{$timestampField} = now();
        $member->save();

        return response()->json([
            'success' => true,
            'data' => $this->mapMember($member->fresh()),
        ]);
    }

    private function applyStatusFilter($query, string $status, Carbon $today, Carbon $soonDate)
    {
        return match ($status) {
            'active' => $query->where('is_active', true)->whereDate('membership_expires_at', '>=', $today),
            'expiring' => $query->where('is_active', true)
                ->whereDate('membership_expires_at', '>=', $today)
                ->whereDate('membership_expires_at', '<=', $soonDate),
            'expired' => $query->where(function ($subQuery) use ($today) {
                $subQuery->where('is_active', false)
                    ->orWhereDate('membership_expires_at', '<', $today);
            }),
            'inactive' => $query->where('is_active', false),
            default => $query,
        };
    }

    private function mapMember(Member $member): array
    {
        $today = now()->startOfDay();
        $expiresAt = $member->membership_expires_at?->copy()->startOfDay();
        $daysUntilExpiry = $expiresAt ? (int) $today->diffInDays($expiresAt, false) : null;

        $status = 'inactive';

        if ((bool) $member->is_active) {
            if ($expiresAt && $expiresAt->lt($today)) {
                $status = 'expired';
            } elseif ($daysUntilExpiry !== null && $daysUntilExpiry <= 30) {
                $status = 'expiring';
            } else {
                $status = 'active';
            }
        } elseif ($expiresAt && $expiresAt->lt($today)) {
            $status = 'expired';
        }

        return [
            'id' => $member->id,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'full_name' => trim($member->first_name . ' ' . $member->last_name),
            'username' => $member->username,
            'email' => $member->email,
            'street' => $member->street,
            'house_number' => $member->house_number,
            'bus' => $member->bus,
            'postal_code' => $member->postal_code,
            'city' => $member->city,
            'full_address' => $this->formatAddress($member),
            'rfid_uid' => $member->rfid_uid,
            'comment' => $member->comment,
            'membership_started_at' => optional($member->membership_started_at)->format('Y-m-d'),
            'membership_expires_at' => optional($member->membership_expires_at)->format('Y-m-d'),
            'membership_started_label' => optional($member->membership_started_at)->format('d/m/Y'),
            'membership_expires_label' => optional($member->membership_expires_at)->format('d/m/Y'),
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

        if (! empty($member->bus)) {
            $line1 = trim($line1 . ' bus ' . $member->bus);
        }

        $line2 = trim(implode(' ', array_filter([
            $member->postal_code,
            $member->city,
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
