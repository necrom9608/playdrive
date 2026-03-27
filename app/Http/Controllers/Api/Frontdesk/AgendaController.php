<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgendaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $view = $request->string('view')->toString() ?: 'day';
        $view = in_array($view, ['day', 'week', 'month'], true) ? $view : 'day';

        $anchorDate = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())->startOfDay()
            : now()->startOfDay();

        [$rangeStart, $rangeEnd] = $this->resolveRange($view, $anchorDate);

        $registrations = Registration::query()
            ->with([
                'eventType:id,name,code,emoji',
                'stayOption:id,name,code,emoji,duration_minutes',
                'cateringOption:id,name,code,emoji',
            ])
            ->whereBetween('event_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->orderBy('id')
            ->get();

        $normalizedRegistrations = $registrations
            ->map(fn (Registration $registration) => $this->transformRegistration($registration))
            ->values();

        $dayRegistrations = $normalizedRegistrations
            ->filter(fn (array $registration) => $registration['event_date'] === $anchorDate->toDateString())
            ->values();

        $days = collect();
        $cursor = $rangeStart->copy();

        while ($cursor->lte($rangeEnd)) {
            $dateKey = $cursor->toDateString();

            $items = $normalizedRegistrations
                ->filter(fn (array $registration) => $registration['event_date'] === $dateKey)
                ->values();

            $days->push([
                'date' => $dateKey,
                'day_number' => $cursor->day,
                'weekday_short' => $cursor->locale('nl_BE')->isoFormat('dd'),
                'weekday_label' => ucfirst($cursor->locale('nl_BE')->isoFormat('dddd')),
                'label' => ucfirst($cursor->locale('nl_BE')->isoFormat('ddd D MMM')),
                'is_today' => $cursor->isSameDay(now()),
                'is_selected' => $cursor->isSameDay($anchorDate),
                'is_current_month' => $cursor->month === $anchorDate->month && $cursor->year === $anchorDate->year,
                'totals' => [
                    'reservations' => $items->count(),
                    'participants' => (int) $items->sum('total_count'),
                    'children' => (int) $items->sum('participants_children'),
                    'adults' => (int) $items->sum('participants_adults'),
                    'supervisors' => (int) $items->sum('participants_supervisors'),
                ],
                'status_totals' => $this->buildStatusTotals($items),
                'catering_totals' => $this->buildCateringTotals($items),
                'registrations' => $view === 'day' ? $items->values() : [],
            ]);

            $cursor->addDay();
        }

        return response()->json([
            'data' => [
                'view' => $view,
                'anchor_date' => $anchorDate->toDateString(),
                'range' => [
                    'start' => $rangeStart->toDateString(),
                    'end' => $rangeEnd->toDateString(),
                    'label' => $this->buildRangeLabel($view, $rangeStart, $rangeEnd, $anchorDate),
                ],
                'summary' => [
                    'reservations' => $normalizedRegistrations->count(),
                    'participants' => (int) $normalizedRegistrations->sum('total_count'),
                    'children' => (int) $normalizedRegistrations->sum('participants_children'),
                    'adults' => (int) $normalizedRegistrations->sum('participants_adults'),
                    'supervisors' => (int) $normalizedRegistrations->sum('participants_supervisors'),
                    'status_totals' => $this->buildStatusTotals($normalizedRegistrations),
                ],
                'day_registrations' => $dayRegistrations,
                'days' => $days->values(),
            ],
        ]);
    }

    protected function resolveRange(string $view, Carbon $anchorDate): array
    {
        return match ($view) {
            'week' => [
                $anchorDate->copy()->startOfWeek(Carbon::MONDAY),
                $anchorDate->copy()->endOfWeek(Carbon::SUNDAY),
            ],
            'month' => [
                $anchorDate->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
                $anchorDate->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
            ],
            default => [$anchorDate->copy(), $anchorDate->copy()],
        };
    }

    protected function buildRangeLabel(string $view, Carbon $rangeStart, Carbon $rangeEnd, Carbon $anchorDate): string
    {
        return match ($view) {
            'week' => ucfirst($rangeStart->locale('nl_BE')->isoFormat('D MMM')) . ' - ' . ucfirst($rangeEnd->locale('nl_BE')->isoFormat('D MMM YYYY')),
            'month' => ucfirst($anchorDate->locale('nl_BE')->isoFormat('MMMM YYYY')),
            default => ucfirst($anchorDate->locale('nl_BE')->isoFormat('dddd D MMMM YYYY')),
        };
    }

    protected function buildStatusTotals($registrations): array
    {
        $options = Registration::statusOptions();

        return collect($options)
            ->map(function (string $label, string $status) use ($registrations) {
                $items = $registrations->where('status', $status);

                return [
                    'key' => $status,
                    'label' => $label,
                    'count' => $items->count(),
                    'people_count' => (int) $items->sum('total_count'),
                    'colors' => $this->statusColor($status),
                ];
            })
            ->filter(fn (array $item) => $item['count'] > 0)
            ->values()
            ->all();
    }

    protected function buildCateringTotals($registrations): array
    {
        return collect($registrations)
            ->filter(fn (array $registration) => !empty($registration['catering_option']))
            ->groupBy('catering_option')
            ->map(function ($items, $label) {
                $first = $items->first();

                return [
                    'key' => $first['catering_option_code'] ?: md5((string) $label),
                    'label' => $label,
                    'emoji' => $first['catering_option_emoji'] ?? null,
                    'count' => $items->count(),
                    'people_count' => (int) $items->sum('total_count'),
                    'badge' => 'bg-cyan-500/15 text-cyan-300 ring-cyan-500/30',
                    'accent' => 'bg-cyan-500',
                ];
            })
            ->sortByDesc('people_count')
            ->values()
            ->all();
    }

    protected function transformRegistration(Registration $registration): array
    {
        $statusLabel = Registration::statusOptions()[$registration->status] ?? 'Onbekend';

        $start = $registration->event_date?->copy()->startOfDay();

        if ($start && $registration->event_time) {
            [$hour, $minute] = array_pad(explode(':', (string) $registration->event_time), 2, '00');
            $start->setTime((int) $hour, (int) $minute);
        }

        $end = $start?->copy();

        if ($end && $registration->stayOption?->duration_minutes) {
            $end->addMinutes((int) $registration->stayOption->duration_minutes);
        }

        $children = (int) $registration->participants_children;
        $adults = (int) $registration->participants_adults;
        $supervisors = (int) $registration->participants_supervisors;
        $totalCount = $children + $adults + $supervisors;

        return [
            'id' => $registration->id,
            'name' => $registration->name,
            'event_date' => optional($registration->event_date)->format('Y-m-d'),
            'event_time' => $registration->event_time ? substr((string) $registration->event_time, 0, 5) : null,
            'start_at' => $start?->toIso8601String(),
            'end_at' => $end?->toIso8601String(),
            'participants_children' => $children,
            'participants_adults' => $adults,
            'participants_supervisors' => $supervisors,
            'total_count' => $totalCount,
            'status' => $registration->status,
            'status_label' => $statusLabel,
            'status_color' => $this->statusColor($registration->status),
            'outside_opening_hours' => (bool) $registration->outside_opening_hours,
            'comment' => $registration->comment,
            'duration_label' => $registration->stayOption?->name,
            'stay_duration_minutes' => $registration->stayOption?->duration_minutes,
            'event_type' => $registration->eventType?->name,
            'event_type_code' => $registration->eventType?->code,
            'event_type_emoji' => $registration->eventType?->emoji,
            'catering_option' => $registration->cateringOption?->name,
            'catering_option_code' => $registration->cateringOption?->code,
            'catering_option_emoji' => $registration->cateringOption?->emoji,
            'invoice_requested' => (bool) $registration->invoice_requested,
            'checked_in_at' => optional($registration->checked_in_at)?->toIso8601String(),
            'checked_out_at' => optional($registration->checked_out_at)?->toIso8601String(),
        ];
    }

    protected function statusColor(?string $status): array
    {
        return match ($status) {
            Registration::STATUS_NEW => [
                'bg' => 'bg-yellow-500/10',
                'border' => 'border-yellow-500/20',
                'text' => 'text-slate-100',
                'accent' => 'bg-yellow-500',
                'badge' => 'bg-yellow-500/15 text-yellow-300 ring-yellow-500/30',
            ],
            Registration::STATUS_CONFIRMED => [
                'bg' => 'bg-orange-500/10',
                'border' => 'border-orange-500/20',
                'text' => 'text-slate-100',
                'accent' => 'bg-orange-500',
                'badge' => 'bg-orange-500/15 text-orange-300 ring-orange-500/30',
            ],
            Registration::STATUS_CHECKED_IN => [
                'bg' => 'bg-blue-500/10',
                'border' => 'border-blue-500/20',
                'text' => 'text-slate-100',
                'accent' => 'bg-blue-500',
                'badge' => 'bg-blue-500/15 text-blue-300 ring-blue-500/30',
            ],
            Registration::STATUS_CHECKED_OUT => [
                'bg' => 'bg-purple-500/10',
                'border' => 'border-purple-500/20',
                'text' => 'text-slate-100',
                'accent' => 'bg-purple-500',
                'badge' => 'bg-purple-500/15 text-purple-300 ring-purple-500/30',
            ],
            Registration::STATUS_PAID => [
                'bg' => 'bg-green-500/10',
                'border' => 'border-green-500/20',
                'text' => 'text-slate-100',
                'accent' => 'bg-green-500',
                'badge' => 'bg-green-500/15 text-green-300 ring-green-500/30',
            ],
            Registration::STATUS_CANCELLED => [
                'bg' => 'bg-slate-500/10',
                'border' => 'border-slate-500/20',
                'text' => 'text-slate-100',
                'accent' => 'bg-slate-500',
                'badge' => 'bg-slate-500/15 text-slate-300 ring-slate-500/30',
            ],
            Registration::STATUS_NO_SHOW => [
                'bg' => 'bg-red-500/10',
                'border' => 'border-red-500/20',
                'text' => 'text-slate-100',
                'accent' => 'bg-red-500',
                'badge' => 'bg-red-500/15 text-red-300 ring-red-500/30',
            ],
            default => [
                'bg' => 'bg-slate-500/10',
                'border' => 'border-slate-500/20',
                'text' => 'text-slate-100',
                'accent' => 'bg-slate-500',
                'badge' => 'bg-slate-500/15 text-slate-300 ring-slate-500/30',
            ],
        };
    }
}
