<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DayTotalsController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        if (! $currentTenant->exists()) {
            return response()->json([
                'message' => 'Geen tenant gevonden.',
            ], 422);
        }

        [$start, $end] = $this->resolvePeriod($request);

        $rows = $this->buildRows($currentTenant->id(), $start, $end);
        $totals = $this->buildTotals($rows);

        return response()->json([
            'data' => [
                'period' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ],
                'rows' => $rows,
                'totals' => $totals,
            ],
        ]);
    }

    public function export(Request $request, CurrentTenant $currentTenant)
    {
        if (! $currentTenant->exists()) {
            abort(422, 'Geen tenant gevonden.');
        }

        [$start, $end] = $this->resolvePeriod($request);
        $rows = $this->buildRows($currentTenant->id(), $start, $end);
        $totals = $this->buildTotals($rows);

        $filename = 'dagtotalen_' . $start->format('Ymd') . '_' . $end->format('Ymd') . '.xls';

        $html = $this->buildExcelHtml($rows, $totals);

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected function resolvePeriod(Request $request): array
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'string'],
            'year' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
        ]);

        $mode = $validated['mode'] ?? 'month';
        $year = (int) ($validated['year'] ?? now()->year);

        if ($mode === 'quarter') {
            $quarter = (int) ($validated['quarter'] ?? 1);
            $startMonth = (($quarter - 1) * 3) + 1;

            $start = Carbon::create($year, $startMonth, 1)->startOfDay();
            $end = (clone $start)->addMonths(2)->endOfMonth()->endOfDay();

            return [$start, $end];
        }

        if ($mode === 'range' && ! empty($validated['start']) && ! empty($validated['end'])) {
            return [
                Carbon::parse($validated['start'])->startOfDay(),
                Carbon::parse($validated['end'])->endOfDay(),
            ];
        }

        $month = (int) ($validated['month'] ?? now()->month);

        $start = Carbon::create($year, $month, 1)->startOfMonth()->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        return [$start, $end];
    }

    protected function buildRows(int $tenantId, Carbon $start, Carbon $end): array
    {
        $items = OrderItem::query()
            ->selectRaw('DATE(orders.paid_at) as order_date')
            ->selectRaw('ROUND(order_items.vat_rate, 0) as vat_rate_rounded')
            ->selectRaw('SUM(order_items.line_subtotal_excl_vat) as total_excl')
            ->selectRaw('SUM(order_items.line_vat) as total_vat')
            ->selectRaw('SUM(order_items.line_total_incl_vat) as total_incl')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$start, $end])
            ->where('orders.status', 'paid')
            ->groupBy(
                DB::raw('DATE(orders.paid_at)'),
                DB::raw('ROUND(order_items.vat_rate, 0)')
            )
            ->orderBy('order_date')
            ->get();

        $grouped = [];

        foreach ($items as $item) {
            $date = $item->order_date;
            $vatRate = (string) ((int) $item->vat_rate_rounded);

            if (! isset($grouped[$date])) {
                $grouped[$date] = [
                    'date' => $date,
                    'total_excl_vat' => 0,
                    'total_vat' => 0,
                    'total_incl_vat' => 0,
                    'vat_breakdown' => [
                        '0' => ['excl' => 0, 'vat' => 0, 'incl' => 0],
                        '6' => ['excl' => 0, 'vat' => 0, 'incl' => 0],
                        '12' => ['excl' => 0, 'vat' => 0, 'incl' => 0],
                        '21' => ['excl' => 0, 'vat' => 0, 'incl' => 0],
                    ],
                ];
            }

            $grouped[$date]['total_excl_vat'] += (float) $item->total_excl;
            $grouped[$date]['total_vat'] += (float) $item->total_vat;
            $grouped[$date]['total_incl_vat'] += (float) $item->total_incl;

            if (! isset($grouped[$date]['vat_breakdown'][$vatRate])) {
                $grouped[$date]['vat_breakdown'][$vatRate] = ['excl' => 0, 'vat' => 0, 'incl' => 0];
            }

            $grouped[$date]['vat_breakdown'][$vatRate]['excl'] += round((float) $item->total_excl, 2);
            $grouped[$date]['vat_breakdown'][$vatRate]['vat'] += round((float) $item->total_vat, 2);
            $grouped[$date]['vat_breakdown'][$vatRate]['incl'] += round((float) $item->total_incl, 2);
        }

        return array_values(array_map(function ($row) {
            $row['total_excl_vat'] = round($row['total_excl_vat'], 2);
            $row['total_vat'] = round($row['total_vat'], 2);
            $row['total_incl_vat'] = round($row['total_incl_vat'], 2);

            foreach ($row['vat_breakdown'] as $rate => $values) {
                $row['vat_breakdown'][$rate]['excl'] = round($values['excl'], 2);
                $row['vat_breakdown'][$rate]['vat'] = round($values['vat'], 2);
                $row['vat_breakdown'][$rate]['incl'] = round($values['incl'], 2);
            }

            return $row;
        }, $grouped));
    }

    protected function buildTotals(array $rows): array
    {
        $totals = [
            'total_excl_vat' => 0,
            'total_vat' => 0,
            'total_incl_vat' => 0,
            'vat_breakdown' => [
                '0' => ['excl' => 0, 'vat' => 0, 'incl' => 0],
                '6' => ['excl' => 0, 'vat' => 0, 'incl' => 0],
                '12' => ['excl' => 0, 'vat' => 0, 'incl' => 0],
                '21' => ['excl' => 0, 'vat' => 0, 'incl' => 0],
            ],
        ];

        foreach ($rows as $row) {
            $totals['total_excl_vat'] += (float) $row['total_excl_vat'];
            $totals['total_vat'] += (float) $row['total_vat'];
            $totals['total_incl_vat'] += (float) $row['total_incl_vat'];

            foreach ($row['vat_breakdown'] as $rate => $values) {
                if (! isset($totals['vat_breakdown'][$rate])) {
                    $totals['vat_breakdown'][$rate] = ['excl' => 0, 'vat' => 0, 'incl' => 0];
                }

                $totals['vat_breakdown'][$rate]['excl'] += (float) $values['excl'];
                $totals['vat_breakdown'][$rate]['vat'] += (float) $values['vat'];
                $totals['vat_breakdown'][$rate]['incl'] += (float) $values['incl'];
            }
        }

        $totals['total_excl_vat'] = round($totals['total_excl_vat'], 2);
        $totals['total_vat'] = round($totals['total_vat'], 2);
        $totals['total_incl_vat'] = round($totals['total_incl_vat'], 2);

        foreach ($totals['vat_breakdown'] as $rate => $values) {
            $totals['vat_breakdown'][$rate]['excl'] = round($values['excl'], 2);
            $totals['vat_breakdown'][$rate]['vat'] = round($values['vat'], 2);
            $totals['vat_breakdown'][$rate]['incl'] = round($values['incl'], 2);
        }

        return $totals;
    }

    protected function buildExcelHtml(array $rows, array $totals): string
    {
        $out = [];
        $out[] = '<html><head><meta charset="UTF-8"></head><body>';
        $out[] = '<table border="1">';
        $out[] = '<tr>';
        $out[] = '<th>Datum</th>';
        $out[] = '<th>Excl. BTW</th>';
        $out[] = '<th>BTW</th>';
        $out[] = '<th>Incl. BTW</th>';
        $out[] = '<th>0% Excl.</th>';
        $out[] = '<th>0% BTW</th>';
        $out[] = '<th>0% Incl.</th>';
        $out[] = '<th>6% Excl.</th>';
        $out[] = '<th>6% BTW</th>';
        $out[] = '<th>6% Incl.</th>';
        $out[] = '<th>12% Excl.</th>';
        $out[] = '<th>12% BTW</th>';
        $out[] = '<th>12% Incl.</th>';
        $out[] = '<th>21% Excl.</th>';
        $out[] = '<th>21% BTW</th>';
        $out[] = '<th>21% Incl.</th>';
        $out[] = '</tr>';

        foreach ($rows as $row) {
            $out[] = '<tr>';
            $out[] = '<td>' . e($row['date']) . '</td>';
            $out[] = '<td>' . $row['total_excl_vat'] . '</td>';
            $out[] = '<td>' . $row['total_vat'] . '</td>';
            $out[] = '<td>' . $row['total_incl_vat'] . '</td>';
            $out[] = '<td>' . ($row['vat_breakdown']['0']['excl'] ?? 0) . '</td>';
            $out[] = '<td>' . ($row['vat_breakdown']['0']['vat'] ?? 0) . '</td>';
            $out[] = '<td>' . ($row['vat_breakdown']['0']['incl'] ?? 0) . '</td>';
            $out[] = '<td>' . ($row['vat_breakdown']['6']['excl'] ?? 0) . '</td>';
            $out[] = '<td>' . ($row['vat_breakdown']['6']['vat'] ?? 0) . '</td>';
            $out[] = '<td>' . ($row['vat_breakdown']['6']['incl'] ?? 0) . '</td>';
            $out[] = '<td>' . ($row['vat_breakdown']['12']['excl'] ?? 0) . '</td>';
            $out[] = '<td>' . ($row['vat_breakdown']['12']['vat'] ?? 0) . '</td>';
            $out[] = '<td>' . ($row['vat_breakdown']['12']['incl'] ?? 0) . '</td>';
            $out[] = '<td>' . ($row['vat_breakdown']['21']['excl'] ?? 0) . '</td>';
            $out[] = '<td>' . ($row['vat_breakdown']['21']['vat'] ?? 0) . '</td>';
            $out[] = '<td>' . ($row['vat_breakdown']['21']['incl'] ?? 0) . '</td>';
            $out[] = '</tr>';
        }

        $out[] = '<tr>';
        $out[] = '<th>Totaal</th>';
        $out[] = '<th>' . $totals['total_excl_vat'] . '</th>';
        $out[] = '<th>' . $totals['total_vat'] . '</th>';
        $out[] = '<th>' . $totals['total_incl_vat'] . '</th>';
        $out[] = '<th>' . ($totals['vat_breakdown']['0']['excl'] ?? 0) . '</th>';
        $out[] = '<th>' . ($totals['vat_breakdown']['0']['vat'] ?? 0) . '</th>';
        $out[] = '<th>' . ($totals['vat_breakdown']['0']['incl'] ?? 0) . '</th>';
        $out[] = '<th>' . ($totals['vat_breakdown']['6']['excl'] ?? 0) . '</th>';
        $out[] = '<th>' . ($totals['vat_breakdown']['6']['vat'] ?? 0) . '</th>';
        $out[] = '<th>' . ($totals['vat_breakdown']['6']['incl'] ?? 0) . '</th>';
        $out[] = '<th>' . ($totals['vat_breakdown']['12']['excl'] ?? 0) . '</th>';
        $out[] = '<th>' . ($totals['vat_breakdown']['12']['vat'] ?? 0) . '</th>';
        $out[] = '<th>' . ($totals['vat_breakdown']['12']['incl'] ?? 0) . '</th>';
        $out[] = '<th>' . ($totals['vat_breakdown']['21']['excl'] ?? 0) . '</th>';
        $out[] = '<th>' . ($totals['vat_breakdown']['21']['vat'] ?? 0) . '</th>';
        $out[] = '<th>' . ($totals['vat_breakdown']['21']['incl'] ?? 0) . '</th>';
        $out[] = '</tr>';

        $out[] = '</table></body></html>';

        return implode('', $out);
    }
}
