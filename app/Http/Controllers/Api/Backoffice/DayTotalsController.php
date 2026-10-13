<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Accounting\AccountingPeriod;
use App\Domain\Accounting\DayTotalsReportService;
use App\Http\Controllers\Controller;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Dagtotalen in de backoffice. De berekening en de Excel zitten in DayTotalsReportService,
 * zodat het scherm, de export en de mail naar de boekhouding altijd hetzelfde tonen.
 */
class DayTotalsController extends Controller
{
    public function __construct(
        private readonly DayTotalsReportService $reports,
    ) {
    }

    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        if (! $currentTenant->exists()) {
            return response()->json(['message' => 'Geen tenant gevonden.'], 422);
        }

        $period = $this->resolvePeriod($request);
        $report = $this->reports->build($currentTenant->id(), $period, $request->boolean('include_invoices'));

        return response()->json([
            'data' => [
                'period' => [
                    'start' => $report['period']['start'],
                    'end' => $report['period']['end'],
                    'label' => $report['period']['label'],
                ],
                'rows' => $report['rows'],
                'totals' => $report['totals'],
                'payments' => $report['payments'],
            ],
        ]);
    }

    public function export(Request $request, CurrentTenant $currentTenant)
    {
        if (! $currentTenant->exists()) {
            abort(422, 'Geen tenant gevonden.');
        }

        $period = $this->resolvePeriod($request);
        $report = $this->reports->build($currentTenant->id(), $period, $request->boolean('include_invoices'));

        $visibleGroups = [
            'rate0' => $request->boolean('show_rate0'),
            'rate6' => $request->boolean('show_rate6', true),
            'rate12' => $request->boolean('show_rate12'),
            'rate21' => $request->boolean('show_rate21', true),
            'total' => $request->boolean('show_total', true),
        ];

        $filename = 'dagtotalen_' . $period->start->format('Ymd') . '_' . $period->end->format('Ymd') . '.xlsx';
        $xlsx = $this->reports->buildXlsx($report, $visibleGroups, $request->boolean('include_payments', true));

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($xlsx),
        ]);
    }

    protected function resolvePeriod(Request $request): AccountingPeriod
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'string'],
            'year' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
            'include_invoices' => ['nullable', 'boolean'],
            'include_payments' => ['nullable', 'boolean'],
            'show_rate0' => ['nullable', 'boolean'],
            'show_rate6' => ['nullable', 'boolean'],
            'show_rate12' => ['nullable', 'boolean'],
            'show_rate21' => ['nullable', 'boolean'],
            'show_total' => ['nullable', 'boolean'],
        ]);

        try {
            return AccountingPeriod::fromInput($validated);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
    }
}
