<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontdesk\StoreMemberRequest;
use App\Http\Requests\Frontdesk\UpdateMemberRequest;
use App\Mail\MemberLifecycleMail;
use App\Models\BadgeTemplate;
use App\Models\Member;
use App\Models\PhysicalCard;
use App\Models\Registration;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

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
                    ->where('id', 'like', '%' . $search . '%')
                    ->orWhere('first_name', 'like', '%' . $search . '%')
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

        $memberIds = $members->pluck('id')->filter()->values();

        $playStats = Registration::query()
            ->selectRaw('member_id')
            ->selectRaw('MAX(COALESCE(checked_out_at, checked_in_at, created_at)) as last_played_at')
            ->selectRaw('COUNT(DISTINCT COALESCE(event_date, DATE(created_at))) as play_days')
            ->selectRaw('SUM(COALESCE(played_minutes, TIMESTAMPDIFF(MINUTE, checked_in_at, checked_out_at), 0)) as play_minutes')
            ->where('tenant_id', $currentTenant->id())
            ->where('is_member', true)
            ->when($memberIds->isNotEmpty(), fn (Builder $builder) => $builder->whereIn('member_id', $memberIds->all()))
            ->groupBy('member_id')
            ->get()
            ->keyBy('member_id');

        $memberCards = PhysicalCard::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('card_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_type', PhysicalCard::TYPE_MEMBER)
            ->whereIn('holder_id', $memberIds->all())
            ->with('badgeTemplate:id,name,template_type,is_default,description')
            ->orderByDesc('id')
            ->get()
            ->groupBy('holder_id')
            ->map(fn ($cards) => $cards->first());

        $baseQuery = Member::query()
            ->where('tenant_id', $currentTenant->id());

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)
                ->where(function (Builder $query) {
                    $query->whereNull('is_active')->orWhere('is_active', true);
                })
                ->whereDate('membership_ends_at', '>', $soonDate)
                ->count(),
            'expiring_soon' => (clone $baseQuery)
                ->where(function (Builder $query) {
                    $query->whereNull('is_active')->orWhere('is_active', true);
                })
                ->whereDate('membership_ends_at', '>=', $today)
                ->whereDate('membership_ends_at', '<=', $soonDate)
                ->count(),
            'expired' => (clone $baseQuery)
                ->where(function (Builder $query) use ($today) {
                    $query->where('is_active', false)
                        ->orWhereNull('membership_ends_at')
                        ->orWhereDate('membership_ends_at', '<', $today);
                })
                ->count(),
        ];

        return response()->json([
            'data' => [
                'summary' => $summary,
                'member_badge_templates' => $this->memberBadgeTemplates($currentTenant),
                'members' => $members->map(fn (Member $member) => $this->mapMember(
                    $member,
                    $today,
                    $soonDate,
                    $playStats->get($member->id),
                    $memberCards->get($member->id),
                ))->values(),
            ],
        ]);
    }

    public function store(StoreMemberRequest $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validated();
        $today = now()->startOfDay();
        $actorUserId = $request->attributes->get('frontdesk_user')?->id;

        $startDate = !empty($data['membership_starts_at'])
            ? Carbon::parse($data['membership_starts_at'])->startOfDay()
            : $today->copy();

        $endsDate = !empty($data['membership_ends_at'])
            ? Carbon::parse($data['membership_ends_at'])->startOfDay()
            : $startDate->copy()->addYear();

        $member = DB::transaction(function () use ($data, $currentTenant, $startDate, $endsDate, $actorUserId) {
            $payload = [
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
                'login' => $this->nullableValue(($data['login'] ?? null) ?: ($data['email'] ?? null)),
                'password' => $this->nullableValue($data['password'] ?? null),
                'rfid_uid' => $this->nullableValue($data['rfid_uid'] ?? null),
                'comment' => $this->nullableValue($data['comment'] ?? null),
                'membership_starts_at' => $startDate->toDateString(),
                'membership_ends_at' => $endsDate->toDateString(),
            ];

            if ($this->membersHasPhoneColumn()) {
                $payload['phone'] = $this->nullableValue($data['phone'] ?? null);
            }

            if ($this->membersHasBirthDateColumn()) {
                $payload['birth_date'] = $this->nullableValue($data['birth_date'] ?? null);
            }

            if ($this->membersHasMembershipTypeColumn()) {
                $payload['membership_type'] = $data['membership_type'] ?? 'adult';
            }

            if ($this->membersHasIsActiveColumn()) {
                $payload['is_active'] = (bool) ($data['is_active'] ?? true);
            }

            $member = Member::query()->create($payload);

            $this->syncMemberCard(
                member: $member,
                currentTenant: $currentTenant,
                actorUserId: $actorUserId,
                badgeTemplateId: $data['badge_template_id'] ?? null,
            );

            return $member;
        });

        $member = $this->loadMemberContext($member, $currentTenant, $today);

        return response()->json([
            'data' => $member,
        ], 201);
    }

    public function update(UpdateMemberRequest $request, CurrentTenant $currentTenant, Member $member): JsonResponse
    {
        abort_unless((int) $member->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validated();
        $today = now()->startOfDay();
        $actorUserId = $request->attributes->get('frontdesk_user')?->id;

        $startDate = !empty($data['membership_starts_at'])
            ? Carbon::parse($data['membership_starts_at'])->startOfDay()
            : ($member->membership_starts_at?->copy()->startOfDay() ?? $today->copy());

        $endsDate = !empty($data['membership_ends_at'])
            ? Carbon::parse($data['membership_ends_at'])->startOfDay()
            : ($member->membership_ends_at?->copy()->startOfDay() ?? $startDate->copy()->addYear());

        DB::transaction(function () use ($member, $data, $startDate, $endsDate, $currentTenant, $actorUserId) {
            $payload = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'street' => $this->nullableValue($data['street'] ?? null),
                'house_number' => $this->nullableValue($data['house_number'] ?? null),
                'box' => $this->nullableValue($data['box'] ?? null),
                'postal_code' => $this->nullableValue($data['postal_code'] ?? null),
                'city' => $this->nullableValue($data['city'] ?? null),
                'country' => $this->nullableValue($data['country'] ?? null),
                'email' => $this->nullableValue($data['email'] ?? null),
                'login' => $this->nullableValue(($data['login'] ?? null) ?: ($data['email'] ?? null)),
                'rfid_uid' => $this->nullableValue($data['rfid_uid'] ?? null),
                'comment' => $this->nullableValue($data['comment'] ?? null),
                'membership_starts_at' => $startDate->toDateString(),
                'membership_ends_at' => $endsDate->toDateString(),
            ];

            if ($this->membersHasPhoneColumn()) {
                $payload['phone'] = $this->nullableValue($data['phone'] ?? null);
            }

            if ($this->membersHasBirthDateColumn()) {
                $payload['birth_date'] = $this->nullableValue($data['birth_date'] ?? null);
            }

            if ($this->membersHasMembershipTypeColumn()) {
                $payload['membership_type'] = $data['membership_type'] ?? ($member->membership_type ?? 'adult');
            }

            if ($this->membersHasIsActiveColumn()) {
                $payload['is_active'] = (bool) ($data['is_active'] ?? true);
            }

            $member->fill($payload);

            if (! empty($data['password'])) {
                $member->password = $data['password'];
            }

            $member->save();

            $this->syncMemberCard(
                member: $member,
                currentTenant: $currentTenant,
                actorUserId: $actorUserId,
                badgeTemplateId: $data['badge_template_id'] ?? null,
            );
        });

        $member = $this->loadMemberContext($member->fresh(), $currentTenant, $today);

        return response()->json([
            'data' => $member,
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

        if ($this->membersHasIsActiveColumn()) {
            $member->is_active = true;
        }

        $member->save();

        $member = $this->loadMemberContext($member->fresh(), $currentTenant, $today);

        return response()->json([
            'data' => $member,
        ]);
    }

    public function toggleAttendance(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validate([
            'member_id' => ['nullable', 'integer'],
            'query' => ['nullable', 'string', 'max:255'],
        ]);

        $member = null;

        if (! empty($data['member_id'])) {
            $member = Member::query()
                ->where('tenant_id', $currentTenant->id())
                ->find($data['member_id']);
        }

        if (! $member && ! empty($data['query'])) {
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

        if (! $member) {
            return response()->json([
                'message' => 'Geen lid gevonden.',
            ], 404);
        }

        $today = now()->startOfDay();
        $endsAt = $member->membership_ends_at?->copy()->startOfDay();
        $isActive = ! $this->membersHasIsActiveColumn() || (bool) $member->is_active;

        if (! $isActive || ($endsAt && $endsAt->lt($today))) {
            return response()->json([
                'message' => 'Dit lid heeft geen actief abonnement.',
                'member' => $this->loadMemberContext($member, $currentTenant, $today),
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
                'participants_children' => 0,
                'participants_adults' => 1,
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
            'member' => $this->loadMemberContext($member->fresh(), $currentTenant, $today),
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
            'data' => $this->loadMemberContext($member->fresh(), $currentTenant, now()->startOfDay()),
        ]);
    }

    private function normalizeStatuses(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }

        if (! is_array($value)) {
            return [];
        }

        $allowed = ['active', 'expiring', 'expired', 'inactive'];

        return collect($value)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => in_array($item, $allowed, true))
            ->unique()
            ->values()
            ->all();
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
                        'active' => $subQuery
                            ->where(function (Builder $builder) {
                                $builder->whereNull('is_active')->orWhere('is_active', true);
                            })
                            ->whereDate('membership_ends_at', '>', $soonDate),
                        'expiring' => $subQuery
                            ->where(function (Builder $builder) {
                                $builder->whereNull('is_active')->orWhere('is_active', true);
                            })
                            ->whereDate('membership_ends_at', '>=', $today)
                            ->whereDate('membership_ends_at', '<=', $soonDate),
                        'expired' => $subQuery->where(function (Builder $builder) use ($today) {
                            $builder
                                ->where('is_active', false)
                                ->orWhereNull('membership_ends_at')
                                ->orWhereDate('membership_ends_at', '<', $today);
                        }),
                        'inactive' => $subQuery->where('is_active', false),
                        default => null,
                    };
                });
            }
        });
    }

    private function mapMember(Member $member, Carbon $today, Carbon $soonDate, mixed $playStat = null, ?PhysicalCard $memberCard = null): array
    {
        $endsAt = $member->membership_ends_at?->copy()->startOfDay();
        $daysUntilExpiry = $endsAt ? (int) $today->diffInDays($endsAt, false) : null;
        $supportsIsActive = $this->membersHasIsActiveColumn();
        $isEnabled = ! $supportsIsActive || (bool) $member->is_active;

        $status = 'inactive';

        if ($isEnabled) {
            if ($endsAt && $endsAt->lt($today)) {
                $status = 'expired';
            } elseif ($endsAt && $endsAt->lte($soonDate)) {
                $status = 'expiring';
            } else {
                $status = 'active';
            }
        } elseif ($endsAt && $endsAt->lt($today)) {
            $status = 'expired';
        }

        $playMinutes = max(0, (int) ($playStat->play_minutes ?? 0));
        $hours = intdiv($playMinutes, 60);
        $minutes = $playMinutes % 60;
        $lastPlayedAt = ! empty($playStat?->last_played_at) ? Carbon::parse($playStat->last_played_at) : null;

        return [
            'id' => $member->id,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'full_name' => trim($member->first_name . ' ' . $member->last_name),
            'login' => $member->login,
            'email' => $member->email,
            'phone' => $member->phone ?? null,
            'birth_date' => optional($member->birth_date)->format('Y-m-d'),
            'membership_type' => $member->membership_type ?? 'adult',
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
            'is_active' => $supportsIsActive ? (bool) $member->is_active : true,
            'last_played_at' => $lastPlayedAt?->toIso8601String(),
            'last_played_label' => $lastPlayedAt?->format('d/m/Y H:i') ?: 'Nog niet gespeeld',
            'play_days' => (int) ($playStat->play_days ?? 0),
            'play_minutes' => $playMinutes,
            'play_hours_label' => sprintf('%du %02dm', $hours, $minutes),
            'member_badge_template_id' => $memberCard?->badge_template_id,
            'member_card_id' => $memberCard?->id,
            'member_card_label' => $memberCard?->label,
            'member_card_status' => $memberCard?->status,
            'member_card_badge_template_name' => $memberCard?->badgeTemplate?->name,
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

        if (! empty($member->box)) {
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

    private function temporaryMemberCardRfid(int $tenantId, int $memberId): string
    {
        return sprintf('TMP-MEMBER-%d-%d', $tenantId, $memberId);
    }

    private function memberBadgeTemplates(CurrentTenant $currentTenant): array
    {
        return BadgeTemplate::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('template_type', PhysicalCard::TYPE_MEMBER)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (BadgeTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'is_default' => (bool) $template->is_default,
                'template_type' => $template->template_type,
            ])
            ->values()
            ->all();
    }

    private function syncMemberCard(Member $member, CurrentTenant $currentTenant, ?int $actorUserId, mixed $badgeTemplateId = null): PhysicalCard
    {
        $card = PhysicalCard::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('card_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_id', $member->id)
            ->latest('id')
            ->first();

        $resolvedBadgeTemplateId = $badgeTemplateId !== null && $badgeTemplateId !== ''
            ? (int) $badgeTemplateId
            : ($card?->badge_template_id ?: $this->defaultMemberBadgeTemplateId($currentTenant));

        $payload = [
            'tenant_id' => $currentTenant->id(),
            'card_type' => PhysicalCard::TYPE_MEMBER,
            'badge_template_id' => $resolvedBadgeTemplateId,
            'holder_type' => PhysicalCard::TYPE_MEMBER,
            'holder_id' => $member->id,
            'label' => sprintf('MEMBER #%d - %s', $member->id, trim($member->first_name . ' ' . $member->last_name)),
            'rfid_uid' => $this->nullableValue($member->rfid_uid) ?: $this->temporaryMemberCardRfid($currentTenant->id(), $member->id),
            'status' => PhysicalCard::STATUS_IN_CIRCULATION,
            'issued_at' => $card?->issued_at ?? now()->startOfDay(),
            'updated_by' => $actorUserId,
        ];

        if ($card) {
            $card->fill($payload);
            $card->save();

            return $card->fresh(['badgeTemplate:id,name,template_type,is_default,description']);
        }

        return PhysicalCard::query()->create([
            ...$payload,
            'created_by' => $actorUserId,
        ])->fresh(['badgeTemplate:id,name,template_type,is_default,description']);
    }

    private function defaultMemberBadgeTemplateId(CurrentTenant $currentTenant): ?int
    {
        return BadgeTemplate::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('template_type', PhysicalCard::TYPE_MEMBER)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');
    }

    private function loadMemberContext(Member $member, CurrentTenant $currentTenant, Carbon $today): array
    {
        $soonDate = $today->copy()->addDays(30);

        $playStat = Registration::query()
            ->selectRaw('member_id')
            ->selectRaw('MAX(COALESCE(checked_out_at, checked_in_at, created_at)) as last_played_at')
            ->selectRaw('COUNT(DISTINCT COALESCE(event_date, DATE(created_at))) as play_days')
            ->selectRaw('SUM(COALESCE(played_minutes, TIMESTAMPDIFF(MINUTE, checked_in_at, checked_out_at), 0)) as play_minutes')
            ->where('tenant_id', $currentTenant->id())
            ->where('is_member', true)
            ->where('member_id', $member->id)
            ->groupBy('member_id')
            ->first();

        $memberCard = PhysicalCard::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('card_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_id', $member->id)
            ->with('badgeTemplate:id,name,template_type,is_default,description')
            ->latest('id')
            ->first();

        return $this->mapMember($member, $today, $soonDate, $playStat, $memberCard);
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
