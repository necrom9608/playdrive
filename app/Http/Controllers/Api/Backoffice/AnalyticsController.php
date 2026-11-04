<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Domain\Display\OpeningHoursToday;
use App\Models\OpeningHour;
use App\Models\Order;
use App\Models\RegionSeason;
use App\Models\Registration;
use App\Models\StaffCheckin;
use App\Support\CurrentTenant;
use App\Support\SchoolHolidayCalendar;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Backoffice-dashboard.
     *
     * Query-parameters (allemaal optioneel):
     *   period      today | week | month (standaard) | custom
     *   start_date  Y-m-d (enkel bij custom)
     *   end_date    Y-m-d (enkel bij custom)
     *   compare     previous_year (standaard) | previous_period
     *
     * Een periode die vandaag eindigt wordt vergeleken tot hetzelfde uur
     * in de vergelijkingsperiode, zodat een halve dag niet tegen een volle dag staat.
     */
    public function dashboard(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_if(! $currentTenant->exists(), 422, 'Geen tenant gevonden voor rapportering.');

        $validated = $request->validate([
            'period' => ['nullable', 'in:today,week,month,custom'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'compare' => ['nullable', 'in:previous_period,previous_year'],
        ]);

        $tenantId = $currentTenant->id();
        $period = $validated['period'] ?? 'month';
        $compare = $validated['compare'] ?? 'previous_year';

        [$start, $end] = $this->resolveDashboardPeriod($period, $validated);
        [$compareStart, $compareEnd] = $this->shiftDashboardRange($period, $compare, $start, $end);

        $primary = $this->buildPeriodSummary($tenantId, $start, $end);
        $comparison = $this->buildPeriodSummary($tenantId, $compareStart, $compareEnd);
        $visitors = $this->visitorCount($tenantId, $start, $end);
        $compareVisitors = $this->visitorCount($tenantId, $compareStart, $compareEnd);

        $perVisitor = $visitors > 0 ? round($primary['revenue'] / $visitors, 2) : null;
        $comparePerVisitor = $compareVisitors > 0 ? round($comparison['revenue'] / $compareVisitors, 2) : null;

        // Trend: de gekozen periode, maar minstens 30 dagen zodat er iets te zien is.
        $trendStart = $start->copy();
        $trendEnd = $end->copy();
        if ($trendStart->diffInDays($trendEnd) < 13) {
            $trendEnd = $end->copy()->endOfDay();
            $trendStart = $trendEnd->copy()->subDays(29)->startOfDay();
        }
        // Vorig jaar: 52 weken terug, zodat weekdagen in de grafiek op elkaar vallen.
        [$trendCompareStart] = $this->shiftDashboardRange($compare === 'previous_year' ? 'week' : 'custom', $compare, $trendStart, $trendEnd);

        return response()->json([
            'data' => [
                'period' => [
                    'key' => $period,
                    'compare' => $compare,
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'compare_start' => $compareStart->toDateString(),
                    'compare_end' => $compareEnd->toDateString(),
                ],
                'kpis' => [
                    'revenue' => $this->kpi($primary['revenue'], $comparison['revenue']),
                    'orders' => $this->kpi($primary['order_count'], $comparison['order_count']) + [
                        'avg_order_value' => $primary['avg_order_value'],
                    ],
                    'visitors' => $this->kpi($visitors, $compareVisitors),
                    'revenue_per_visitor' => $this->kpi($perVisitor, $comparePerVisitor),
                ],
                'trend' => $this->dashboardTrend(
                    $currentTenant,
                    $trendStart,
                    $trendEnd,
                    $trendCompareStart
                ),
                'payment_breakdown' => $this->paymentBreakdown($tenantId, $start, $end),
                'category_breakdown' => $this->categoryBreakdown($tenantId, $start, $end, 6),
                'source_breakdown' => $this->sourceBreakdown($tenantId, $start, $end),
                'top_products' => $this->topProducts($tenantId, $start, $end, 6),
                'today' => $this->dashboardToday($tenantId),
                'holiday_comparisons' => $this->holidayComparisons($tenantId),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function resolveDashboardPeriod(string $period, array $validated): array
    {
        $today = now()->startOfDay();

        return match ($period) {
            'today' => [$today->copy(), $today->copy()->endOfDay()],
            'week' => [$today->copy()->startOfWeek(Carbon::MONDAY), $today->copy()->endOfDay()],
            'custom' => (function () use ($validated, $today) {
                $start = isset($validated['start_date'])
                    ? Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay()
                    : $today->copy()->startOfMonth();
                $end = isset($validated['end_date'])
                    ? Carbon::createFromFormat('Y-m-d', $validated['end_date'])->endOfDay()
                    : $today->copy()->endOfDay();

                if ($end->lt($start)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }

                // Max. 2 jaar, zodat één klik de server niet vastzet.
                if ($start->diffInDays($end) > 731) {
                    $start = $end->copy()->subDays(731)->startOfDay();
                }

                return [$start, $end];
            })(),
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
        };
    }

    /**
     * Vergelijkingsperiode met dezelfde lengte.
     * Dag- en weekvergelijkingen met vorig jaar schuiven 52 weken op, zodat
     * een woensdag met een woensdag vergeleken wordt.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function shiftDashboardRange(string $period, string $compare, Carbon $start, Carbon $end): array
    {
        $days = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;

        if ($compare === 'previous_year') {
            $compareStart = in_array($period, ['today', 'week'], true)
                ? $start->copy()->subWeeks(52)
                : $start->copy()->subYearNoOverflow();
        } else {
            $compareStart = match ($period) {
                'today', 'week' => $start->copy()->subWeek(),
                'month' => $start->copy()->subMonthNoOverflow()->startOfMonth(),
                default => $start->copy()->subDays($days),
            };
        }

        $compareStart = $compareStart->startOfDay();
        $compareEnd = $compareStart->copy()->addDays($days - 1)->endOfDay();

        // Loopt de periode tot vandaag: vergelijk tot hetzelfde uur.
        if ($end->isToday()) {
            $compareEnd = $compareEnd->copy()->setTimeFrom(now());
        }

        return [$compareStart, $compareEnd];
    }

    protected function kpi(int|float|null $value, int|float|null $compare): array
    {
        $delta = null;
        if ($value !== null && $compare !== null && (float) $compare != 0.0) {
            $delta = round((($value - $compare) / abs($compare)) * 100, 1);
        }

        return [
            'value' => $value,
            'compare' => $compare,
            'delta_percentage' => $delta,
        ];
    }

    /** Aantal bezoekers = deelnemers van reservaties die effectief kwamen. */
    protected function visitorCount(int $tenantId, Carbon $start, Carbon $end): int
    {
        return (int) Registration::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', [
                Registration::STATUS_CHECKED_IN,
                Registration::STATUS_CHECKED_OUT,
                Registration::STATUS_PAID,
            ])
            ->selectRaw('COALESCE(SUM(COALESCE(participants_children,0) + COALESCE(participants_adults,0) + COALESCE(participants_supervisors,0)), 0) as total')
            ->value('total');
    }

    /** Netto-omzet per dag (of per week bij lange periodes) in één query. */
    protected function revenueByDay(int $tenantId, Carbon $start, Carbon $end): array
    {
        return DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', Order::STATUS_PAID)
            ->whereNull('cancelled_at')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('DATE(paid_at) as day_key')
            ->selectRaw('SUM(CASE WHEN COALESCE(refund_amount,0) > 0 AND total_incl_vat >= 0 THEN total_incl_vat - refund_amount ELSE total_incl_vat END) as revenue')
            ->selectRaw('COUNT(*) as order_count')
            ->groupByRaw('DATE(paid_at)')
            ->get()
            ->keyBy('day_key')
            ->map(fn ($row) => ['revenue' => round((float) $row->revenue, 2), 'orders' => (int) $row->order_count])
            ->all();
    }

    protected function dashboardTrend(CurrentTenant $currentTenant, Carbon $start, Carbon $end, Carbon $compareStart): array
    {
        $tenantId = $currentTenant->id();
        $dayCount = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        $compareEnd = $compareStart->copy()->addDays($dayCount - 1)->endOfDay();

        $revenue = $this->revenueByDay($tenantId, $start, $end);
        $compareRevenue = $this->revenueByDay($tenantId, $compareStart, $compareEnd);
        $holidays = $this->holidayRanges($currentTenant, $start, $end);

        // Openingsuren enkel per dag opvragen bij korte reeksen (3 queries/dag).
        $openingHours = null;
        if ($dayCount <= 62 && $currentTenant->tenant && OpeningHour::query()->where('tenant_id', $tenantId)->exists()) {
            $openingHours = new OpeningHoursToday($currentTenant->tenant);
        }

        $days = [];
        for ($i = 0; $i < $dayCount; $i++) {
            $day = $start->copy()->startOfDay()->addDays($i);
            $compareDay = $compareStart->copy()->startOfDay()->addDays($i);
            $key = $day->toDateString();
            $value = $revenue[$key]['revenue'] ?? 0.0;

            $isOpen = $value != 0.0;
            if (! $isOpen && $openingHours) {
                $isOpen = (bool) ($openingHours->forDate(CarbonImmutable::parse($key))['is_open'] ?? false);
            }

            $holiday = null;
            foreach ($holidays as $range) {
                if ($key >= $range['start'] && $key <= $range['end']) {
                    $holiday = $range['label'];
                    break;
                }
            }

            $days[] = [
                'date' => $key,
                'value' => $value,
                'orders' => $revenue[$key]['orders'] ?? 0,
                'compare_date' => $compareDay->toDateString(),
                'compare_value' => $compareRevenue[$compareDay->toDateString()]['revenue'] ?? 0.0,
                'is_weekend' => $day->isWeekend(),
                'is_open' => $isOpen,
                'is_today' => $day->isToday(),
                'holiday' => $holiday,
            ];
        }

        $bucket = 'day';
        if ($dayCount > 120) {
            $bucket = 'week';
            $days = collect($days)
                ->groupBy(fn ($d) => Carbon::parse($d['date'])->startOfWeek(Carbon::MONDAY)->toDateString())
                ->map(fn ($group, $weekStart) => [
                    'date' => $weekStart,
                    'value' => round($group->sum('value'), 2),
                    'orders' => $group->sum('orders'),
                    'compare_date' => $group->first()['compare_date'],
                    'compare_value' => round($group->sum('compare_value'), 2),
                    'is_weekend' => false,
                    'is_open' => $group->contains(fn ($d) => $d['is_open']),
                    'is_today' => $group->contains(fn ($d) => $d['is_today']),
                    'holiday' => $group->pluck('holiday')->filter()->first(),
                ])
                ->values()
                ->all();
        }

        $values = collect($days)->pluck('value');
        $openDays = collect($days)->filter(fn ($d) => $d['is_open'] || $d['value'] != 0.0)->count();
        $best = collect($days)->sortByDesc('value')->first();

        return [
            'bucket' => $bucket,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'compare_start' => $compareStart->toDateString(),
            'compare_end' => $compareEnd->toDateString(),
            'total' => round($values->sum(), 2),
            'compare_total' => round(collect($days)->sum('compare_value'), 2),
            'average_per_open_day' => $openDays > 0 ? round($values->sum() / $openDays, 2) : 0,
            'best' => $best && $best['value'] > 0 ? ['date' => $best['date'], 'value' => $best['value']] : null,
            'holidays' => $holidays,
            'points' => $days,
        ];
    }

    /**
     * Vakantieperiodes in een bereik: eerst de regiokalender van de tenant
     * (Admin → Regio's), anders de vaste Vlaamse schoolkalender.
     */
    protected function holidayRanges(CurrentTenant $currentTenant, Carbon $start, Carbon $end): array
    {
        $from = $start->toDateString();
        $until = $end->toDateString();
        $regionCode = $currentTenant->tenant?->region_code;

        if ($regionCode) {
            $seasons = RegionSeason::query()
                ->where('region_code', $regionCode)
                ->where('season_key', '!=', 'regular')
                ->whereDate('date_from', '<=', $until)
                ->whereDate('date_until', '>=', $from)
                ->orderBy('date_from')
                ->get();

            if ($seasons->isNotEmpty()) {
                return $seasons->map(fn (RegionSeason $season) => [
                    'label' => $season->season_name ?: $season->season_key,
                    'start' => $season->date_from->toDateString(),
                    'end' => $season->date_until->toDateString(),
                ])->values()->all();
            }
        }

        $ranges = [];
        foreach (SchoolHolidayCalendar::all() as $holidays) {
            foreach ($holidays as $holiday) {
                if ($holiday['start'] <= $until && $holiday['end'] >= $from) {
                    $ranges[] = ['label' => $holiday['label'], 'start' => $holiday['start'], 'end' => $holiday['end']];
                }
            }
        }

        usort($ranges, fn ($a, $b) => strcmp($a['start'], $b['start']));

        return $ranges;
    }

    /** Live-blok "Vandaag": omzet, reservaties en personeel. */
    protected function dashboardToday(int $tenantId): array
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();

        $revenue = $this->buildPeriodSummary($tenantId, $todayStart, $now->copy()->endOfDay());
        $lastWeek = $this->buildPeriodSummary($tenantId, $todayStart->copy()->subWeek(), $now->copy()->subWeek());

        $reservations = Registration::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('event_date', $todayStart->toDateString())
            ->whereNotIn('status', [Registration::STATUS_CANCELLED])
            ->with(['eventType:id,name', 'stayOption:id,name'])
            ->orderByRaw('event_time IS NULL, event_time')
            ->get();

        $tomorrow = Registration::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('event_date', $todayStart->copy()->addDay()->toDateString())
            ->whereNotIn('status', [Registration::STATUS_CANCELLED, Registration::STATUS_NO_SHOW])
            ->get(['participants_children', 'participants_adults', 'participants_supervisors']);

        $sessions = StaffCheckin::query()
            ->where('tenant_id', $tenantId)
            ->where('checked_in_at', '<=', $now)
            ->where(fn ($q) => $q->whereNull('checked_out_at')->orWhere('checked_out_at', '>=', $todayStart))
            ->get(['user_id', 'checked_in_at', 'checked_out_at']);

        $staffMinutes = $sessions->sum(function (StaffCheckin $session) use ($todayStart, $now) {
            $from = $session->checked_in_at->greaterThan($todayStart) ? $session->checked_in_at : $todayStart;
            $until = $session->checked_out_at && $session->checked_out_at->lessThan($now) ? $session->checked_out_at : $now;

            return max(0, (int) $from->diffInMinutes($until));
        });
        $staffHours = round($staffMinutes / 60, 2);

        return [
            'revenue' => $this->kpi($revenue['revenue'], $lastWeek['revenue']) + [
                'order_count' => $revenue['order_count'],
                'compare_date' => $todayStart->copy()->subWeek()->toDateString(),
            ],
            'reservations' => $reservations->map(fn (Registration $registration) => [
                'id' => $registration->id,
                'name' => $registration->name,
                'time' => $registration->event_time ? substr((string) $registration->event_time, 0, 5) : null,
                'participants' => $registration->total_participants,
                'event_type' => $registration->eventTypeLabel(),
                'stay_option' => $registration->stayOption?->name,
                'status' => $registration->status,
                'status_label' => $registration->status_label,
            ])->values()->all(),
            'tomorrow' => [
                'reservation_count' => $tomorrow->count(),
                'participants' => $tomorrow->sum(fn (Registration $registration) => $registration->total_participants),
            ],
            'staff' => [
                'active_count' => $sessions->whereNull('checked_out_at')->pluck('user_id')->unique()->count(),
                'hours' => $staffHours,
                'revenue_per_hour' => $staffHours > 0 ? round($revenue['revenue'] / $staffHours, 2) : null,
            ],
        ];
    }

    public function reporting(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_if(! $currentTenant->exists(), 422, 'Geen tenant gevonden voor rapportering.');

        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'compare_start_date' => ['nullable', 'date_format:Y-m-d'],
            'compare_end_date' => ['nullable', 'date_format:Y-m-d'],
            'compare_mode' => ['nullable', 'string', 'max:50'],
            'metric' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'holiday_key' => ['nullable', 'string', 'max:50'],
            'holiday_year' => ['nullable', 'integer'],
            'holiday_compare_year' => ['nullable', 'integer'],
        ]);

        $filters = [
            'source' => $validated['source'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
        ];

        $metric = $validated['metric'] ?? 'revenue';
        [$primaryPeriod, $comparisonPeriod] = $this->resolvePeriods($validated);

        return response()->json([
            'data' => [
                'metric' => $metric,
                'primary_period' => $primaryPeriod,
                'comparison_period' => $comparisonPeriod,
                'summary' => [
                    'primary' => $this->buildPeriodSummary(
                        $currentTenant->id(),
                        Carbon::parse($primaryPeriod['start'])->startOfDay(),
                        Carbon::parse($primaryPeriod['end'])->endOfDay(),
                        $filters
                    ),
                    'comparison' => $this->buildPeriodSummary(
                        $currentTenant->id(),
                        Carbon::parse($comparisonPeriod['start'])->startOfDay(),
                        Carbon::parse($comparisonPeriod['end'])->endOfDay(),
                        $filters
                    ),
                ],
                'series' => [
                    'primary' => $this->buildOrderSeries(
                        $currentTenant->id(),
                        Carbon::parse($primaryPeriod['start'])->startOfDay(),
                        Carbon::parse($primaryPeriod['end'])->endOfDay(),
                        $metric,
                        $filters
                    ),
                    'comparison' => $this->buildOrderSeries(
                        $currentTenant->id(),
                        Carbon::parse($comparisonPeriod['start'])->startOfDay(),
                        Carbon::parse($comparisonPeriod['end'])->endOfDay(),
                        $metric,
                        $filters
                    ),
                ],
                'category_breakdown' => $this->categoryBreakdown(
                    $currentTenant->id(),
                    Carbon::parse($primaryPeriod['start'])->startOfDay(),
                    Carbon::parse($primaryPeriod['end'])->endOfDay(),
                    12,
                    $filters
                ),
                'top_products' => $this->topProducts(
                    $currentTenant->id(),
                    Carbon::parse($primaryPeriod['start'])->startOfDay(),
                    Carbon::parse($primaryPeriod['end'])->endOfDay(),
                    12,
                    $filters
                ),
                'reservation_overview' => $this->reservationOverview(
                    $currentTenant->id(),
                    Carbon::parse($primaryPeriod['start'])->startOfDay(),
                    Carbon::parse($primaryPeriod['end'])->endOfDay()
                ),
                'source_breakdown' => $this->sourceBreakdown(
                    $currentTenant->id(),
                    Carbon::parse($primaryPeriod['start'])->startOfDay(),
                    Carbon::parse($primaryPeriod['end'])->endOfDay(),
                    $filters
                ),
                'holiday_options' => SchoolHolidayCalendar::options(),
            ],
        ]);
    }

    protected function resolvePeriods(array $validated): array
    {
        $holidayKey = $validated['holiday_key'] ?? null;
        $holidayYear = isset($validated['holiday_year']) ? (int) $validated['holiday_year'] : null;
        $holidayCompareYear = isset($validated['holiday_compare_year']) ? (int) $validated['holiday_compare_year'] : null;

        if ($holidayKey && $holidayYear && $holidayCompareYear) {
            $primaryHoliday = SchoolHolidayCalendar::find($holidayKey, $holidayYear);
            $comparisonHoliday = SchoolHolidayCalendar::find($holidayKey, $holidayCompareYear);

            if ($primaryHoliday && $comparisonHoliday) {
                return [
                    $primaryHoliday,
                    $comparisonHoliday,
                ];
            }
        }

        $start = isset($validated['start_date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay()
            : now()->startOfMonth();

        $end = isset($validated['end_date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['end_date'])->endOfDay()
            : now()->endOfDay();

        $compareMode = $validated['compare_mode'] ?? 'previous_period';

        if (! empty($validated['compare_start_date']) && ! empty($validated['compare_end_date'])) {
            $compareStart = Carbon::createFromFormat('Y-m-d', $validated['compare_start_date'])->startOfDay();
            $compareEnd = Carbon::createFromFormat('Y-m-d', $validated['compare_end_date'])->endOfDay();
        } else {
            $days = max($start->diffInDays($end), 0) + 1;

            if ($compareMode === 'same_period_last_year') {
                $compareStart = $start->copy()->subYear();
                $compareEnd = $end->copy()->subYear();
            } else {
                $compareEnd = $start->copy()->subDay()->endOfDay();
                $compareStart = $compareEnd->copy()->subDays($days - 1)->startOfDay();
            }
        }

        return [
            [
                'label' => sprintf('%s t.e.m. %s', $start->format('d/m/Y'), $end->format('d/m/Y')),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'days' => $start->diffInDays($end) + 1,
            ],
            [
                'label' => sprintf('%s t.e.m. %s', $compareStart->format('d/m/Y'), $compareEnd->format('d/m/Y')),
                'start' => $compareStart->toDateString(),
                'end' => $compareEnd->toDateString(),
                'days' => $compareStart->diffInDays($compareEnd) + 1,
            ],
        ];
    }

    protected function buildPeriodSummary(int $tenantId, Carbon $start, Carbon $end, array $filters = []): array
    {
        $orders = $this->basePaidOrdersQuery($tenantId, $filters)
            ->whereBetween('paid_at', [$start, $end])
            ->get([
                'id',
                'source',
                'payment_method',
                'invoice_requested',
                'total_incl_vat',
                'refund_amount',
                'registration_id',
                'paid_at',
            ]);

        $revenue = round((float) $orders->sum(fn (Order $order) => $this->netRevenueValue($order)), 2);
        $orderCount = $orders->count();
        $avgOrderValue = $orderCount > 0 ? round($revenue / $orderCount, 2) : 0.0;

        $itemStats = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.tenant_id', $tenantId)
            ->where('o.status', Order::STATUS_PAID)
            ->whereNull('o.cancelled_at')
            ->whereBetween('o.paid_at', [$start, $end]);

        if (! empty($filters['source'])) {
            $itemStats->where('o.source', $filters['source']);
        }

        if (! empty($filters['payment_method'])) {
            $itemStats->where('o.payment_method', $filters['payment_method']);
        }

        $itemStats = $itemStats
            ->selectRaw('COALESCE(SUM(oi.quantity), 0) as quantity_sum, COALESCE(SUM(oi.line_total_incl_vat), 0) as revenue_sum')
            ->first();

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'days' => $start->diffInDays($end) + 1,
            'revenue' => $revenue,
            'order_count' => $orderCount,
            'avg_order_value' => $avgOrderValue,
            'cash_revenue' => round((float) $orders->where('payment_method', 'cash')->sum(fn (Order $order) => $this->netRevenueValue($order)), 2),
            'card_revenue' => round((float) $orders->whereIn('payment_method', ['card', 'bancontact'])->sum(fn (Order $order) => $this->netRevenueValue($order)), 2),
            'invoice_revenue' => round((float) $orders->where('invoice_requested', true)->sum(fn (Order $order) => $this->netRevenueValue($order)), 2),
            'item_quantity' => (int) round((float) ($itemStats->quantity_sum ?? 0)),
            'item_revenue' => round((float) ($itemStats->revenue_sum ?? 0), 2),
            'sources' => [
                'loyverse' => $orders->where('source', 'loyverse')->count(),
                'walk_in' => $orders->where('source', Order::SOURCE_WALK_IN)->count(),
                'reservation' => $orders->where('source', Order::SOURCE_RESERVATION)->count(),
            ],
        ];
    }

    protected function buildOrderSeries(int $tenantId, Carbon $start, Carbon $end, string $metric = 'revenue', array $filters = []): array
    {
        $orders = $this->basePaidOrdersQuery($tenantId, $filters)
            ->whereBetween('paid_at', [$start, $end])
            ->get(['id', 'paid_at', 'total_incl_vat', 'refund_amount']);

        $itemCounts = [];
        if ($metric === 'items') {
            $itemCounts = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->where('o.tenant_id', $tenantId)
                ->where('o.status', Order::STATUS_PAID)
                ->whereNull('o.cancelled_at')
                ->whereBetween('o.paid_at', [$start, $end])
                ->when(! empty($filters['source']), fn ($q) => $q->where('o.source', $filters['source']))
                ->when(! empty($filters['payment_method']), fn ($q) => $q->where('o.payment_method', $filters['payment_method']))
                ->selectRaw('DATE(o.paid_at) as date_key, SUM(oi.quantity) as quantity_sum')
                ->groupByRaw('DATE(o.paid_at)')
                ->pluck('quantity_sum', 'date_key')
                ->all();
        }

        $days = [];
        $cursor = $start->copy()->startOfDay();
        $index = 1;

        while ($cursor->lte($end)) {
            $sameDayOrders = $orders->filter(fn (Order $order) => optional($order->paid_at)?->isSameDay($cursor));

            $value = match ($metric) {
                'orders' => $sameDayOrders->count(),
                'avg_order_value' => $sameDayOrders->count() > 0
                    ? round((float) $sameDayOrders->sum(fn (Order $order) => $this->netRevenueValue($order)) / $sameDayOrders->count(), 2)
                    : 0,
                'items' => round((float) ($itemCounts[$cursor->toDateString()] ?? 0), 2),
                default => round((float) $sameDayOrders->sum(fn (Order $order) => $this->netRevenueValue($order)), 2),
            };

            $days[] = [
                'index' => $index,
                'date' => $cursor->toDateString(),
                'label' => $cursor->translatedFormat('D d/m'),
                'weekday' => $cursor->translatedFormat('l'),
                'value' => $value,
            ];

            $cursor->addDay();
            $index++;
        }

        return $days;
    }

    protected function paymentBreakdown(int $tenantId, Carbon $start, Carbon $end): array
    {
        $orders = $this->basePaidOrdersQuery($tenantId)
            ->whereBetween('paid_at', [$start, $end])
            ->get(['payment_method', 'total_incl_vat', 'refund_amount']);

        return $orders
            ->groupBy(fn (Order $order) => $order->payment_method ?: 'onbekend')
            ->map(fn ($group, $key) => [
                'key' => $key,
                'label' => match ($key) {
                    'cash' => 'Cash',
                    'card' => 'Kaart',
                    'bancontact' => 'Bancontact',
                    default => ucfirst((string) $key),
                },
                'amount' => round((float) $group->sum(fn (Order $order) => $this->netRevenueValue($order)), 2),
                'count' => $group->count(),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    protected function categoryBreakdown(int $tenantId, Carbon $start, Carbon $end, int $limit = 10, array $filters = []): array
    {
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.product_category_id')
            ->where('o.tenant_id', $tenantId)
            ->where('o.status', Order::STATUS_PAID)
            ->whereNull('o.cancelled_at')
            ->whereBetween('o.paid_at', [$start, $end])
            ->when(! empty($filters['source']), fn ($q) => $q->where('o.source', $filters['source']))
            ->when(! empty($filters['payment_method']), fn ($q) => $q->where('o.payment_method', $filters['payment_method']))
            ->selectRaw("COALESCE(pc.name, 'Onbekend') as category_label")
            ->selectRaw('SUM(oi.line_total_incl_vat) as revenue')
            ->selectRaw('SUM(oi.quantity) as quantity')
            ->groupByRaw("COALESCE(pc.name, 'Onbekend')")
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => [
            'label' => $row->category_label,
            'revenue' => round((float) $row->revenue, 2),
            'quantity' => (int) round((float) $row->quantity),
        ])->values()->all();
    }

    protected function topProducts(int $tenantId, Carbon $start, Carbon $end, int $limit = 10, array $filters = []): array
    {
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.tenant_id', $tenantId)
            ->where('o.status', Order::STATUS_PAID)
            ->whereNull('o.cancelled_at')
            ->whereBetween('o.paid_at', [$start, $end])
            ->when(! empty($filters['source']), fn ($q) => $q->where('o.source', $filters['source']))
            ->when(! empty($filters['payment_method']), fn ($q) => $q->where('o.payment_method', $filters['payment_method']))
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.product_category_id')
            ->selectRaw('oi.name as product_name')
            ->selectRaw("COALESCE(pc.name, 'Onbekend') as category_label")
            ->selectRaw('SUM(oi.quantity) as quantity')
            ->selectRaw('SUM(oi.line_total_incl_vat) as revenue')
            ->groupBy('oi.name', 'pc.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => [
            'label' => $row->product_name,
            'category' => $row->category_label,
            'quantity' => (int) round((float) $row->quantity),
            'revenue' => round((float) $row->revenue, 2),
        ])->values()->all();
    }

    protected function reservationOverview(int $tenantId, Carbon $start, Carbon $end): array
    {
        $registrations = Registration::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->with(['eventType:id,name', 'cateringOption:id,name'])
            ->get();

        $totalVisitors = $registrations->sum(fn (Registration $registration) => $registration->total_participants);

        return [
            'reservation_count' => $registrations->count(),
            'visitor_count' => $totalVisitors,
            'no_show_count' => $registrations->where('status', Registration::STATUS_NO_SHOW)->count(),
            'cancelled_count' => $registrations->where('status', Registration::STATUS_CANCELLED)->count(),
            'checked_in_count' => $registrations->where('status', Registration::STATUS_CHECKED_IN)->count(),
            'average_group_size' => $registrations->count() > 0 ? round($totalVisitors / $registrations->count(), 1) : 0,
            'event_types' => $registrations
                ->groupBy(fn (Registration $registration) => $registration->eventType?->name ?: 'Onbekend')
                ->map(fn ($group, $key) => ['label' => $key, 'count' => $group->count()])
                ->sortByDesc('count')
                ->values()
                ->take(6)
                ->all(),
            'catering' => $registrations
                ->groupBy(fn (Registration $registration) => $registration->cateringOption?->name ?: 'Geen catering')
                ->map(fn ($group, $key) => ['label' => $key, 'count' => $group->count()])
                ->sortByDesc('count')
                ->values()
                ->take(6)
                ->all(),
        ];
    }

    protected function sourceBreakdown(int $tenantId, Carbon $start, Carbon $end, array $filters = []): array
    {
        $orders = $this->basePaidOrdersQuery($tenantId, $filters)
            ->whereBetween('paid_at', [$start, $end])
            ->get(['source', 'total_incl_vat', 'refund_amount']);

        return $orders
            ->groupBy(fn (Order $order) => $order->source ?: 'onbekend')
            ->map(fn ($group, $key) => [
                'key' => $key,
                'label' => match ($key) {
                    'loyverse' => 'Loyverse import',
                    Order::SOURCE_RESERVATION => 'Reservatie',
                    Order::SOURCE_WALK_IN => 'Losse verkoop',
                    default => ucfirst((string) $key),
                },
                'count' => $group->count(),
                'revenue' => round((float) $group->sum(fn (Order $order) => $this->netRevenueValue($order)), 2),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    protected function holidayComparisons(int $tenantId): array
    {
        $now = now();
        $items = [];

        foreach (['carnival', 'easter', 'summer', 'autumn', 'christmas'] as $key) {
            $current = SchoolHolidayCalendar::find($key, (int) $now->format('Y'));
            $previous = SchoolHolidayCalendar::find($key, (int) $now->copy()->subYear()->format('Y'));

            if (! $current || ! $previous) {
                continue;
            }

            if (Carbon::parse($current['end'])->isFuture()) {
                continue;
            }

            $currentSummary = $this->buildPeriodSummary(
                $tenantId,
                Carbon::parse($current['start'])->startOfDay(),
                Carbon::parse($current['end'])->endOfDay()
            );
            $previousSummary = $this->buildPeriodSummary(
                $tenantId,
                Carbon::parse($previous['start'])->startOfDay(),
                Carbon::parse($previous['end'])->endOfDay()
            );

            $delta = $previousSummary['revenue'] != 0
                ? round((($currentSummary['revenue'] - $previousSummary['revenue']) / $previousSummary['revenue']) * 100, 1)
                : null;

            $items[] = [
                'key' => $key,
                'label' => $current['label'],
                'current' => $currentSummary,
                'previous' => $previousSummary,
                'delta_percentage' => $delta,
            ];
        }

        return $items;
    }

    protected function basePaidOrdersQuery(int $tenantId, array $filters = []): Builder
    {
        return Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', Order::STATUS_PAID)
            ->whereNull('cancelled_at')
            ->when(! empty($filters['source']), fn ($query) => $query->where('source', $filters['source']))
            ->when(! empty($filters['payment_method']), fn ($query) => $query->where('payment_method', $filters['payment_method']));
    }

    protected function netRevenueValue(Order $order): float
    {
        $total = (float) $order->total_incl_vat;
        $refundAmount = (float) ($order->refund_amount ?? 0);

        if ($refundAmount > 0 && $total >= 0) {
            return round($total - $refundAmount, 2);
        }

        return round($total, 2);
    }
}
