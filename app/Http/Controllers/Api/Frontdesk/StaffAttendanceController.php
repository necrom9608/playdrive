<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\StaffCheckin;
use App\Models\User;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffAttendanceController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenantId = (int) $currentTenant->id();
        $today = Carbon::today();

        $activeCheckins = StaffCheckin::query()
            ->with(['staff'])
            ->where('tenant_id', $tenantId)
            ->whereNull('checked_out_at')
            ->orderBy('checked_in_at')
            ->get();

        $todaySessions = StaffCheckin::query()
            ->with(['staff', 'checkedInBy', 'checkedOutBy'])
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($today) {
                $query->whereDate('checked_in_at', $today)
                    ->orWhereDate('checked_out_at', $today);
            })
            ->orderByDesc('checked_in_at')
            ->limit(50)
            ->get();

        $activeStaffCount = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $checkedInTodayCount = StaffCheckin::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('checked_in_at', $today)
            ->distinct('user_id')
            ->count('user_id');

        $checkedOutTodayCount = StaffCheckin::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('checked_out_at', $today)
            ->distinct('user_id')
            ->count('user_id');

        $lastAction = $todaySessions->first();

        return response()->json([
            'stats' => [
                'active_users' => $activeStaffCount,
                'checked_in_now' => $activeCheckins->count(),
                'started_today' => $checkedInTodayCount,
                'checked_out_today' => $checkedOutTodayCount,
            ],
            'active_sessions' => $activeCheckins
                ->map(fn (StaffCheckin $checkin) => $this->mapActiveSession($checkin))
                ->values(),
            'today_sessions' => $todaySessions
                ->map(fn (StaffCheckin $checkin) => $this->mapTodaySession($checkin))
                ->values(),
            'last_action' => $lastAction ? $this->mapLastAction($lastAction) : null,
        ]);
    }

    public function scan(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validate([
            'rfid_uid' => ['required', 'string', 'max:100'],
        ]);

        $tenantId = (int) $currentTenant->id();
        $uid = $this->normalizeUid($data['rfid_uid']);

        if ($uid === '') {
            return response()->json([
                'message' => 'Rfid uid is verplicht.',
                'errors' => [
                    'rfid_uid' => ['Rfid uid is verplicht.'],
                ],
            ], 422);
        }

        $staff = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->first(function (User $user) use ($uid) {
                return $this->normalizeUid($user->rfid_uid) === $uid;
            });

        if (! $staff) {
            return response()->json([
                'message' => 'Onbekende of niet-actieve personeelskaart.',
                'errors' => [
                    'rfid_uid' => ['Onbekende of niet-actieve personeelskaart.'],
                ],
            ], 422);
        }

        $openCheckin = StaffCheckin::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $staff->id)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        if ($openCheckin) {
            $openCheckin->forceFill([
                'checked_out_at' => now(),
                'checked_out_by' => $request->user()?->id,
            ])->save();

            return $this->buildFullResponse(
                $tenantId,
                $openCheckin->fresh(['staff', 'checkedInBy', 'checkedOutBy']),
                'check_out'
            );
        }

        $checkin = StaffCheckin::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $staff->id,
            'rfid_uid' => $uid,
            'checked_in_at' => now(),
            'checked_in_by' => $request->user()?->id,
        ]);

        return $this->buildFullResponse(
            $tenantId,
            $checkin->fresh(['staff', 'checkedInBy', 'checkedOutBy']),
            'check_in',
            201
        );
    }

    private function buildFullResponse(int $tenantId, StaffCheckin $latestEntry, string $action, int $status = 200): JsonResponse
    {
        $today = Carbon::today();

        $activeCheckins = StaffCheckin::query()
            ->with(['staff'])
            ->where('tenant_id', $tenantId)
            ->whereNull('checked_out_at')
            ->orderBy('checked_in_at')
            ->get();

        $todaySessions = StaffCheckin::query()
            ->with(['staff', 'checkedInBy', 'checkedOutBy'])
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($today) {
                $query->whereDate('checked_in_at', $today)
                    ->orWhereDate('checked_out_at', $today);
            })
            ->orderByDesc('checked_in_at')
            ->limit(50)
            ->get();

        $activeStaffCount = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $checkedInTodayCount = StaffCheckin::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('checked_in_at', $today)
            ->distinct('user_id')
            ->count('user_id');

        $checkedOutTodayCount = StaffCheckin::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('checked_out_at', $today)
            ->distinct('user_id')
            ->count('user_id');

        return response()->json([
            'stats' => [
                'active_users' => $activeStaffCount,
                'checked_in_now' => $activeCheckins->count(),
                'started_today' => $checkedInTodayCount,
                'checked_out_today' => $checkedOutTodayCount,
            ],
            'active_sessions' => $activeCheckins
                ->map(fn (StaffCheckin $checkin) => $this->mapActiveSession($checkin))
                ->values(),
            'today_sessions' => $todaySessions
                ->map(fn (StaffCheckin $checkin) => $this->mapTodaySession($checkin))
                ->values(),
            'last_action' => $this->mapLastAction($latestEntry, $action),
        ], $status);
    }

    private function mapActiveSession(StaffCheckin $checkin): array
    {
        return [
            'id' => $checkin->id,
            'user_name' => $checkin->staff?->name ?? 'Onbekend',
            'rfid_uid' => $this->normalizeUid($checkin->rfid_uid),
            'checked_in_at_label' => $checkin->checked_in_at?->format('H:i'),
            'duration_label' => $this->formatDuration($checkin->checked_in_at, null),
        ];
    }

    private function mapTodaySession(StaffCheckin $checkin): array
    {
        return [
            'id' => $checkin->id,
            'user_name' => $checkin->staff?->name ?? 'Onbekend',
            'rfid_uid' => $this->normalizeUid($checkin->rfid_uid),
            'checked_in_at_full_label' => $checkin->checked_in_at?->format('d/m/Y H:i'),
            'checked_out_at_full_label' => $checkin->checked_out_at?->format('d/m/Y H:i'),
            'duration_label' => $this->formatDuration($checkin->checked_in_at, $checkin->checked_out_at),
            'is_active' => $checkin->checked_out_at === null,
            'processed_by_name' => $checkin->checked_out_at
                ? $checkin->checkedOutBy?->name
                : $checkin->checkedInBy?->name,
        ];
    }

    private function mapLastAction(StaffCheckin $checkin, ?string $forcedAction = null): array
    {
        $action = $forcedAction ?? ($checkin->checked_out_at ? 'check_out' : 'check_in');

        return [
            'action' => $action,
            'message' => $action === 'check_out' ? 'Uitcheck geregistreerd' : 'Check-in geregistreerd',
            'description' => ($checkin->staff?->name ?? 'Onbekend') . ($action === 'check_out' ? ' is uitgecheckt.' : ' is ingecheckt.'),
            'user_name' => $checkin->staff?->name ?? 'Onbekend',
            'action_label' => $action === 'check_out' ? 'Uitgecheckt' : 'Ingecheckt',
            'time_label' => ($action === 'check_out'
                    ? $checkin->checked_out_at
                    : $checkin->checked_in_at)?->diffForHumans() ?? 'zonet',
        ];
    }

    private function formatDuration(?Carbon $start, ?Carbon $end = null): ?string
    {
        if (! $start) {
            return null;
        }

        $finish = $end ?? now();
        $minutes = max(1, $start->diffInMinutes($finish));
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0) {
            return sprintf('%du %02d min', $hours, $remainingMinutes);
        }

        return sprintf('%d min', $remainingMinutes);
    }

    private function normalizeUid(?string $value): string
    {
        $cleaned = strtolower((string) preg_replace('/[^a-fA-F0-9]/', '', (string) $value));

        if ($cleaned === '') {
            return '';
        }

        if (strlen($cleaned) === 16) {
            $pairs = str_split($cleaned, 2);
            $collapsed = '';

            foreach ($pairs as $index => $pair) {
                if ($index % 2 === 0) {
                    $collapsed .= $pair;
                }
            }

            return $collapsed;
        }

        if (strlen($cleaned) > 8) {
            return substr($cleaned, -8);
        }

        return $cleaned;
    }
}
