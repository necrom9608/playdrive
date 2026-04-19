<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\BadgeTemplate;
use App\Models\PhysicalCard;
use App\Models\Registration;
use App\Models\TenantMembership;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ledenmodule — werkt volledig op TenantMembership + Account.
 * De legacy members-tabel wordt niet meer aangeraakt.
 */
class MemberController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $search           = trim((string) $request->input('search', ''));
        $selectedStatuses = $this->normalizeStatuses($request->input('selected_statuses', []));
        $days             = max(1, (int) $request->input('expiring_within_days', 30));

        $today    = now()->startOfDay();
        $soonDate = now()->copy()->startOfDay()->addDays($days);

        $query = TenantMembership::query()
            ->where('tenant_memberships.tenant_id', $currentTenant->id())
            ->with('account:id,first_name,last_name,email,phone,birth_date,created_at');

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('rfid_uid', 'like', '%' . $search . '%')
                  ->orWhereHas('account', fn (Builder $aq) => $aq
                      ->where('first_name', 'like', '%' . $search . '%')
                      ->orWhere('last_name', 'like', '%' . $search . '%')
                      ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ['%' . $search . '%'])
                      ->orWhere('email', 'like', '%' . $search . '%')
                  );
            });
        }

        $this->applyStatusFilter($query, $selectedStatuses, $today, $soonDate);

        $memberships = $query
            ->orderByRaw('membership_ends_at is null')
            ->orderBy('membership_ends_at', 'asc')
            ->orderByRaw('(select last_name from accounts where accounts.id = tenant_memberships.account_id) asc')
            ->get();

        $membershipIds = $memberships->pluck('id')->all();
        $accountIds    = $memberships->pluck('account_id')->filter()->unique()->values()->all();

        $playStats = Registration::query()
            ->selectRaw('account_id')
            ->selectRaw('MAX(COALESCE(checked_out_at, checked_in_at, created_at)) as last_played_at')
            ->selectRaw('COUNT(DISTINCT COALESCE(event_date, DATE(created_at))) as play_days')
            ->selectRaw('SUM(COALESCE(played_minutes, TIMESTAMPDIFF(MINUTE, checked_in_at, checked_out_at), 0)) as play_minutes')
            ->where('tenant_id', $currentTenant->id())
            ->where('is_member', true)
            ->whereIn('account_id', $accountIds)
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $cards = PhysicalCard::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('card_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_type', PhysicalCard::TYPE_MEMBER)
            ->whereIn('holder_id', $membershipIds)
            ->with('badgeTemplate:id,name,template_type,is_default,description')
            ->orderByDesc('id')
            ->get()
            ->groupBy('holder_id')
            ->map(fn ($group) => $group->first());

        $base = fn () => TenantMembership::query()->where('tenant_id', $currentTenant->id());

        $summary = [
            'total'         => ($base)()->count(),
            'none'          => ($base)()->where('is_active', false)->whereNull('membership_ends_at')->count(),
            'active'        => ($base)()->where('is_active', true)->whereDate('membership_ends_at', '>', $soonDate)->count(),
            'expiring_soon' => ($base)()->where('is_active', true)
                ->whereDate('membership_ends_at', '>=', $today)
                ->whereDate('membership_ends_at', '<=', $soonDate)
                ->count(),
            'expired'       => ($base)()
                ->where(fn ($q) => $q
                    ->where(fn ($a) => $a->where('is_active', false)->whereNotNull('membership_ends_at'))
                    ->orWhere(fn ($b) => $b->where('is_active', true)->whereDate('membership_ends_at', '<', $today))
                )->count(),
        ];

        return response()->json([
            'data' => [
                'summary'                => $summary,
                'member_badge_templates' => $this->badgeTemplates($currentTenant),
                'members'                => $memberships->map(fn (TenantMembership $m) => $this->mapMembership(
                    $m, $today, $soonDate,
                    $playStats->get($m->account_id),
                    $cards->get($m->id),
                ))->values(),
            ],
        ]);
    }

    public function update(Request $request, CurrentTenant $currentTenant, TenantMembership $tenantMembership): JsonResponse
    {
        abort_unless((int) $tenantMembership->tenant_id === (int) $currentTenant->id(), 404);

        $today   = now()->startOfDay();
        $actorId = $request->attributes->get('frontdesk_user')?->id;

        $data = $request->validate([
            'membership_type'      => ['sometimes', 'nullable', 'in:adult,student'],
            'rfid_uid'             => ['sometimes', 'nullable', 'string', 'max:100'],
            'membership_starts_at' => ['sometimes', 'nullable', 'date'],
            'membership_ends_at'   => ['sometimes', 'nullable', 'date'],
            'is_active'            => ['sometimes', 'boolean'],
            'badge_template_id'    => ['sometimes', 'nullable', 'integer'],
        ]);

        DB::transaction(function () use ($tenantMembership, $data, $currentTenant, $actorId) {
            $fill = [];
            if (array_key_exists('membership_type', $data))      $fill['membership_type']      = $data['membership_type'] ?? $tenantMembership->membership_type;
            if (array_key_exists('rfid_uid', $data))             $fill['rfid_uid']             = $data['rfid_uid'] ?: null;
            if (array_key_exists('membership_starts_at', $data)) $fill['membership_starts_at'] = $data['membership_starts_at'];
            if (array_key_exists('membership_ends_at', $data))   $fill['membership_ends_at']   = $data['membership_ends_at'];
            if (array_key_exists('is_active', $data))            $fill['is_active']            = $data['is_active'];

            if (! empty($fill)) {
                $tenantMembership->update($fill);
            }

            if (array_key_exists('badge_template_id', $data) || array_key_exists('rfid_uid', $data)) {
                $this->syncCard($tenantMembership->fresh(), $currentTenant, $actorId, $data['badge_template_id'] ?? null);
            }
        });

        return response()->json(['data' => $this->loadMembership($tenantMembership->fresh(), $currentTenant, $today)]);
    }

    public function renew(CurrentTenant $currentTenant, TenantMembership $tenantMembership): JsonResponse
    {
        abort_unless((int) $tenantMembership->tenant_id === (int) $currentTenant->id(), 404);

        $today = now()->startOfDay();

        $newStart = $tenantMembership->membership_ends_at && $tenantMembership->membership_ends_at->greaterThanOrEqualTo($today)
            ? $tenantMembership->membership_ends_at->copy()->addDay()
            : $today->copy();

        $tenantMembership->update([
            'membership_starts_at' => $newStart->toDateString(),
            'membership_ends_at'   => $newStart->copy()->addYear()->toDateString(),
            'is_active'            => true,
        ]);

        return response()->json(['data' => $this->loadMembership($tenantMembership->fresh(), $currentTenant, $today)]);
    }

    public function toggleAttendance(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validate([
            'membership_id' => ['nullable', 'integer'],
            'query'         => ['nullable', 'string', 'max:255'],
        ]);

        $membership = null;

        if (! empty($data['membership_id'])) {
            $membership = TenantMembership::query()
                ->where('tenant_id', $currentTenant->id())
                ->with('account')
                ->find($data['membership_id']);
        }

        if (! $membership && ! empty($data['query'])) {
            $search = trim((string) $data['query']);
            $membership = TenantMembership::query()
                ->where('tenant_id', $currentTenant->id())
                ->where(fn (Builder $q) => $q
                    ->where('rfid_uid', 'like', '%' . $search . '%')
                    ->orWhereHas('account', fn (Builder $aq) => $aq
                        ->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ['%' . $search . '%'])
                        ->orWhere('email', 'like', '%' . $search . '%')
                    )
                )
                ->with('account')
                ->first();
        }

        if (! $membership) {
            return response()->json(['message' => 'Geen lid gevonden.'], 404);
        }

        $today  = now()->startOfDay();
        $endsAt = $membership->membership_ends_at?->copy()->startOfDay();

        if (! $membership->is_active || ! $endsAt || $endsAt->lt($today)) {
            return response()->json([
                'message' => 'Dit lid heeft geen actief abonnement.',
                'member'  => $this->loadMembership($membership, $currentTenant, $today),
            ], 422);
        }

        $actorId = $request->attributes->get('frontdesk_user')?->id;

        $result = DB::transaction(function () use ($membership, $currentTenant, $actorId) {
            TenantMembership::query()->where('id', $membership->id)->lockForUpdate()->first();

            $open = Registration::query()
                ->where('tenant_id', $currentTenant->id())
                ->where('is_member', true)
                ->where('account_id', $membership->account_id)
                ->where('status', Registration::STATUS_CHECKED_IN)
                ->whereDate('checked_in_at', now()->toDateString())
                ->latest('id')
                ->first();

            if ($open) {
                $paid = ($open->bill_total_cents ?? 0) > 0;
                $open->update([
                    'status'         => $paid ? Registration::STATUS_CHECKED_OUT : Registration::STATUS_PAID,
                    'checked_out_at' => now(),
                    'checked_out_by' => $actorId,
                    'updated_by'     => $actorId,
                    'played_minutes' => $open->checked_in_at ? max(0, $open->checked_in_at->diffInMinutes(now())) : 0,
                ]);
                return ['registration' => $open->fresh(), 'action' => 'checked_out'];
            }

            $account      = $membership->account;
            $registration = Registration::query()->create([
                'tenant_id'               => $currentTenant->id(),
                'name'                    => trim(($account?->first_name ?? '') . ' ' . ($account?->last_name ?? '')),
                'email'                   => $account?->email,
                'postal_code'             => $account?->postal_code,
                'municipality'            => $account?->city,
                'event_date'              => now()->toDateString(),
                'event_time'              => now()->format('H:i'),
                'participants_children'   => 0,
                'participants_adults'     => 1,
                'participants_supervisors' => 0,
                'comment'                 => 'Lidbezoek',
                'stats'                   => [],
                'status'                  => Registration::STATUS_CHECKED_IN,
                'invoice_requested'       => false,
                'checked_in_at'           => now(),
                'checked_in_by'           => $actorId,
                'created_by'              => $actorId,
                'updated_by'              => $actorId,
                'played_minutes'          => 0,
                'bill_total_cents'        => 0,
                'outside_opening_hours'   => false,
                'is_member'               => true,
                'account_id'              => $membership->account_id,
            ]);

            return ['registration' => $registration, 'action' => 'checked_in'];
        });

        $reg    = $result['registration'];
        $action = $result['action'];
        $isPaid = $action === 'checked_out' && $reg->status === Registration::STATUS_PAID;

        return response()->json([
            'message'      => $action === 'checked_out' ? ($isPaid ? 'Lid uitgecheckt en betaald.' : 'Lid uitgecheckt.') : 'Lid ingecheckt.',
            'action'       => $action,
            'auto_paid'    => $isPaid,
            'member'       => $this->loadMembership($membership->fresh(), $currentTenant, $today),
            'registration' => [
                'id'               => $reg->id,
                'status'           => $reg->status,
                'checked_in_at'    => optional($reg->checked_in_at)?->toIso8601String(),
                'checked_out_at'   => optional($reg->checked_out_at)?->toIso8601String(),
                'played_minutes'   => $reg->played_minutes,
                'bill_total_cents' => $reg->bill_total_cents ?? 0,
                'is_member'        => true,
                'account_id'       => $reg->account_id,
            ],
        ]);
    }

    // ------------------------------------------------------------------

    private function mapMembership(TenantMembership $m, Carbon $today, Carbon $soonDate, mixed $playStat = null, ?PhysicalCard $card = null): array
    {
        $account = $m->account;
        $endsAt  = $m->membership_ends_at?->copy()->startOfDay();
        $daysUntilExpiry = $endsAt ? (int) $today->diffInDays($endsAt, false) : null;

        $status = 'none';
        if ($m->is_active && $endsAt) {
            $status = match (true) {
                $endsAt->lt($today)    => 'expired',
                $endsAt->lte($soonDate) => 'expiring',
                default                => 'active',
            };
        } elseif (! $m->is_active && $endsAt?->lt($today)) {
            $status = 'expired';
        }

        $playMinutes = max(0, (int) ($playStat->play_minutes ?? 0));
        $lastPlayed  = ! empty($playStat?->last_played_at) ? Carbon::parse($playStat->last_played_at) : null;

        return [
            'id'                              => $m->id,
            'account_id'                      => $m->account_id,
            'first_name'                      => $account?->first_name,
            'last_name'                       => $account?->last_name,
            'full_name'                       => trim(($account?->first_name ?? '') . ' ' . ($account?->last_name ?? '')),
            'email'                           => $account?->email,
            'phone'                           => $account?->phone,
            'birth_date'                      => $account?->birth_date?->toDateString(),
            'membership_type'                 => $m->membership_type,
            'rfid_uid'                        => $m->rfid_uid,
            'membership_starts_at'            => $m->membership_starts_at?->toDateString(),
            'membership_ends_at'              => $m->membership_ends_at?->toDateString(),
            'membership_started_label'        => $m->membership_starts_at?->format('d/m/Y'),
            'membership_expires_label'        => $m->membership_ends_at?->format('d/m/Y'),
            'days_until_expiry'               => $daysUntilExpiry,
            'status'                          => $status,
            'is_active'                       => (bool) $m->is_active,
            'is_new'                          => $m->created_at && $m->created_at->diffInHours(now()) < 24,
            'last_played_at'                  => $lastPlayed?->toIso8601String(),
            'last_played_label'               => $lastPlayed?->format('d/m/Y H:i') ?: 'Nog niet gespeeld',
            'play_days'                       => (int) ($playStat->play_days ?? 0),
            'play_minutes'                    => $playMinutes,
            'play_hours_label'                => sprintf('%du %02dm', intdiv($playMinutes, 60), $playMinutes % 60),
            'member_badge_template_id'        => $card?->badge_template_id,
            'member_card_id'                  => $card?->id,
            'member_card_label'               => $card?->label,
            'member_card_status'              => $card?->status,
            'member_card_badge_template_name' => $card?->badgeTemplate?->name,
        ];
    }

    private function loadMembership(TenantMembership $m, CurrentTenant $currentTenant, Carbon $today): array
    {
        $m->loadMissing('account:id,first_name,last_name,email,phone,birth_date,created_at');
        $soonDate = $today->copy()->addDays(30);

        $playStat = Registration::query()
            ->selectRaw('account_id')
            ->selectRaw('MAX(COALESCE(checked_out_at, checked_in_at, created_at)) as last_played_at')
            ->selectRaw('COUNT(DISTINCT COALESCE(event_date, DATE(created_at))) as play_days')
            ->selectRaw('SUM(COALESCE(played_minutes, TIMESTAMPDIFF(MINUTE, checked_in_at, checked_out_at), 0)) as play_minutes')
            ->where('tenant_id', $currentTenant->id())
            ->where('is_member', true)
            ->where('account_id', $m->account_id)
            ->groupBy('account_id')
            ->first();

        $card = PhysicalCard::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('card_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_id', $m->id)
            ->with('badgeTemplate:id,name,template_type,is_default,description')
            ->latest('id')
            ->first();

        return $this->mapMembership($m, $today, $soonDate, $playStat, $card);
    }

    private function syncCard(TenantMembership $m, CurrentTenant $currentTenant, ?int $actorId, mixed $badgeTemplateId = null): void
    {
        $card = PhysicalCard::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('card_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_id', $m->id)
            ->latest('id')
            ->first();

        $templateId = $badgeTemplateId !== null && $badgeTemplateId !== ''
            ? (int) $badgeTemplateId
            : ($card?->badge_template_id ?: BadgeTemplate::query()
                ->where('tenant_id', $currentTenant->id())
                ->where('template_type', PhysicalCard::TYPE_MEMBER)
                ->orderByDesc('is_default')->orderBy('id')
                ->value('id'));

        $account = $m->account;
        $payload = [
            'tenant_id'         => $currentTenant->id(),
            'card_type'         => PhysicalCard::TYPE_MEMBER,
            'badge_template_id' => $templateId,
            'holder_type'       => PhysicalCard::TYPE_MEMBER,
            'holder_id'         => $m->id,
            'label'             => sprintf('LID #%d - %s', $m->id, trim(($account?->first_name ?? '') . ' ' . ($account?->last_name ?? ''))),
            'rfid_uid'          => $m->rfid_uid ?: sprintf('TMP-MEMBER-%d-%d', $currentTenant->id(), $m->id),
            'status'            => PhysicalCard::STATUS_IN_CIRCULATION,
            'issued_at'         => $card?->issued_at ?? now()->startOfDay(),
            'updated_by'        => $actorId,
        ];

        $card ? $card->fill($payload)->save() : PhysicalCard::query()->create([...$payload, 'created_by' => $actorId]);
    }

    private function badgeTemplates(CurrentTenant $currentTenant): array
    {
        return BadgeTemplate::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('template_type', PhysicalCard::TYPE_MEMBER)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (BadgeTemplate $t) => [
                'id'            => $t->id,
                'name'          => $t->name,
                'description'   => $t->description,
                'is_default'    => (bool) $t->is_default,
                'template_type' => $t->template_type,
                'config_json'   => $t->config_json ?? [],
            ])
            ->values()
            ->all();
    }

    private function normalizeStatuses(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }
        $allowed = ['none', 'active', 'expiring', 'expired', 'inactive'];
        return collect(is_array($value) ? $value : [])
            ->map(fn ($s) => trim((string) $s))
            ->filter(fn ($s) => in_array($s, $allowed, true))
            ->unique()->values()->all();
    }

    private function applyStatusFilter(Builder $query, array $statuses, Carbon $today, Carbon $soonDate): void
    {
        if (empty($statuses)) return;

        $query->where(function (Builder $q) use ($statuses, $today, $soonDate) {
            foreach ($statuses as $status) {
                $q->orWhere(function (Builder $sub) use ($status, $today, $soonDate) {
                    match ($status) {
                        'none'     => $sub->where('is_active', false)->whereNull('membership_ends_at'),
                        'active'   => $sub->where('is_active', true)->whereDate('membership_ends_at', '>', $soonDate),
                        'expiring' => $sub->where('is_active', true)
                            ->whereDate('membership_ends_at', '>=', $today)
                            ->whereDate('membership_ends_at', '<=', $soonDate),
                        'expired'  => $sub->where(fn ($x) => $x
                            ->where(fn ($a) => $a->where('is_active', false)->whereNotNull('membership_ends_at'))
                            ->orWhere(fn ($b) => $b->where('is_active', true)->whereDate('membership_ends_at', '<', $today))
                        ),
                        'inactive' => $sub->where('is_active', false),
                        default    => null,
                    };
                });
            }
        });
    }
}
