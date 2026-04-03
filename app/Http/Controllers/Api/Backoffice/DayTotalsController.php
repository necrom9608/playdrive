
<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Style\StyleBuilder;
use OpenSpout\Writer\XLSX\Writer;

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

        return response()->json([
            'data' => [
                'period' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ],
                'rows' => $rows,
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

        $fileName = 'dagtotalen_' . $start->format('Ymd') . '_' . $end->format('Ymd') . '.xlsx';
        $filePath = storage_path('app/' . $fileName);

        $writer = new Writer();
        $writer->openToFile($filePath);

        $headerStyle = (new StyleBuilder())->setFontBold()->build();

        $headers = [
            'Datum','Excl. BTW','BTW','Incl. BTW',
            'BTW 0% Excl.','BTW 0% BTW','BTW 0% Incl.',
            'BTW 6% Excl.','BTW 6% BTW','BTW 6% Incl.',
            'BTW 12% Excl.','BTW 12% BTW','BTW 12% Incl.',
            'BTW 21% Excl.','BTW 21% BTW','BTW 21% Incl.',
        ];

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($headers, $headerStyle));

        foreach ($rows as $row) {
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                $row['date'],
                $row['total_excl_vat'],
                $row['total_vat'],
                $row['total_incl_vat'],
                $row['vat_breakdown']['0']['excl'] ?? 0,
                $row['vat_breakdown']['0']['vat'] ?? 0,
                $row['vat_breakdown']['0']['incl'] ?? 0,
                $row['vat_breakdown']['6']['excl'] ?? 0,
                $row['vat_breakdown']['6']['vat'] ?? 0,
                $row['vat_breakdown']['6']['incl'] ?? 0,
                $row['vat_breakdown']['12']['excl'] ?? 0,
                $row['vat_breakdown']['12']['vat'] ?? 0,
                $row['vat_breakdown']['12']['incl'] ?? 0,
                $row['vat_breakdown']['21']['excl'] ?? 0,
                $row['vat_breakdown']['21']['vat'] ?? 0,
                $row['vat_breakdown']['21']['incl'] ?? 0,
            ]));
        }

        $writer->close();

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    protected function resolvePeriod(Request $request): array
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'string'],
            'month' => ['nullable', 'string'],
            'quarter' => ['nullable', 'string'],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
        ]);

        $mode = $validated['mode'] ?? 'month';

        if ($mode === 'quarter' && ! empty($validated['quarter'])) {
            [$year, $quarter] = explode('-Q', $validated['quarter']);
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

        $month = $validated['month'] ?? now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->startOfDay();
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
            ->groupBy(DB::raw('DATE(orders.paid_at)'), DB::raw('ROUND(order_items.vat_rate, 0)'))
            ->orderBy('order_date')
            ->get();

        $grouped = [];

        foreach ($items as $item) {
            $date = $item->order_date;
            $vatRate = (string)((int)$item->vat_rate_rounded);

            if (! isset($grouped[$date])) {
                $grouped[$date] = [
                    'date' => $date,
                    'total_excl_vat' => 0,
                    'total_vat' => 0,
                    'total_incl_vat' => 0,
                    'vat_breakdown' => [],
                ];
            }

            $grouped[$date]['total_excl_vat'] += (float)$item->total_excl;
            $grouped[$date]['total_vat'] += (float)$item->total_vat;
            $grouped[$date]['total_incl_vat'] += (float)$item->total_incl;

            $grouped[$date]['vat_breakdown'][$vatRate] = [
                'excl' => round((float)$item->total_excl, 2),
                'vat' => round((float)$item->total_vat, 2),
                'incl' => round((float)$item->total_incl, 2),
            ];
        }

        return array_values(array_map(function ($row) {
            $row['total_excl_vat'] = round($row['total_excl_vat'], 2);
            $row['total_vat'] = round($row['total_vat'], 2);
            $row['total_incl_vat'] = round($row['total_incl_vat'], 2);
            return $row;
        }, $grouped));
    }
}
