<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Registration;
use App\Support\CurrentTenant;
use App\Support\SchoolHolidayCalendar;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function dashboard(CurrentTenant $currentTenant): JsonResponse
    {
        abort_if(! $currentTenant->exists(), 422, 'Geen tenant gevonden voor rapportering.');

        $today = now()->startOfDay();
        $monthStart = $today->copy()->startOfMonth();
        $yearStart = $today->copy()->startOfYear();

        $summary = [
            'today' => $this->buildPeriodSummary($currentTenant->id(), $today, $today->copy()->endOfDay()),
            'month' => $this->buildPeriodSummary($currentTenant->id(), $monthStart, $today->copy()->endOfDay()),
            'year' => $this->buildPeriodSummary($currentTenant->id(), $yearStart, $today->copy()->endOfDay()),
        ];

        return response()->json([
            'data' => [
                'summary' => $summary,
                'revenue_trend' => $this->buildOrderSeries(
                    $currentTenant->id(),
                    now()->subDays(29)->startOfDay(),
                    now()->endOfDay(),
                    'revenue'
                ),
                'previous_revenue_trend' => $this->buildOrderSeries(
                    $currentTenant->id(),
                    now()->subDays(59)->startOfDay(),
                    now()->subDays(30)->endOfDay(),
                    'revenue'
                ),
                'payment_breakdown' => $this->paymentBreakdown($currentTenant->id(), $monthStart, now()->endOfDay()),
                'category_breakdown' => $this->categoryBreakdown($currentTenant->id(), $monthStart, now()->endOfDay(), 8),
                'top_products' => $this->topProducts($currentTenant->id(), $monthStart, now()->endOfDay(), 8),
                'reservation_overview' => $this->reservationOverview($currentTenant->id(), $monthStart, now()->endOfDay()),
                'source_breakdown' => $this->sourceBreakdown($currentTenant->id(), $monthStart, now()->endOfDay()),
                'holiday_comparisons' => $this->holidayComparisons($currentTenant->id()),
            ],
        ]);
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
            ->selectRaw("COALESCE(pc.name, oi.legacy_category, 'Onbekend') as category_label")
            ->selectRaw('SUM(oi.line_total_incl_vat) as revenue')
            ->selectRaw('SUM(oi.quantity) as quantity')
            ->groupByRaw("COALESCE(pc.name, oi.legacy_category, 'Onbekend')")
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
            ->selectRaw('oi.name as product_name')
            ->selectRaw("COALESCE(oi.legacy_category, 'Onbekend') as category_label")
            ->selectRaw('SUM(oi.quantity) as quantity')
            ->selectRaw('SUM(oi.line_total_incl_vat) as revenue')
            ->groupBy('oi.name', 'oi.legacy_category')
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
