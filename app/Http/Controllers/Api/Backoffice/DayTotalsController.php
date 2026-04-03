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

        $rows = $this->buildRows($currentTenant->id(), $start, $end, $this->includeInvoices($request));
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
        $rows = $this->buildRows($currentTenant->id(), $start, $end, $this->includeInvoices($request));
        $totals = $this->buildTotals($rows);
        $visibleGroups = $this->resolveVisibleGroups($request);

        $filename = 'dagtotalen_' . $start->format('Ymd') . '_' . $end->format('Ymd') . '.xlsx';
        $xlsx = $this->buildXlsxBinary($rows, $totals, $visibleGroups);

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($xlsx),
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
            'include_invoices' => ['nullable', 'boolean'],
            'show_rate0' => ['nullable', 'boolean'],
            'show_rate6' => ['nullable', 'boolean'],
            'show_rate12' => ['nullable', 'boolean'],
            'show_rate21' => ['nullable', 'boolean'],
            'show_total' => ['nullable', 'boolean'],
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

    protected function includeInvoices(Request $request): bool
    {
        return $request->boolean('include_invoices');
    }

    protected function resolveVisibleGroups(Request $request): array
    {
        return [
            'rate0' => $request->boolean('show_rate0'),
            'rate6' => $request->boolean('show_rate6', true),
            'rate12' => $request->boolean('show_rate12'),
            'rate21' => $request->boolean('show_rate21', true),
            'total' => $request->boolean('show_total', true),
        ];
    }

    protected function buildRows(int $tenantId, Carbon $start, Carbon $end, bool $includeInvoices = false): array
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
            ->where('orders.status', 'paid');

        if (! $includeInvoices) {
            $items->where('orders.invoice_requested', false);
        }

        $items = $items->groupBy(
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

    protected function buildXlsxBinary(array $rows, array $totals, array $visibleGroups): string
    {
        if (! class_exists(\ZipArchive::class)) {
            abort(500, 'ZipArchive is niet beschikbaar op deze server.');
        }

        [$sheetRows, $merges, $columnCount] = $this->buildSheetRows($rows, $totals, $visibleGroups);
        $sheetXml = $this->buildWorksheetXml($sheetRows, $merges, $columnCount);

        $tmp = tempnam(sys_get_temp_dir(), 'daytotals_xlsx_');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->buildContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->buildRootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->buildStylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $binary = file_get_contents($tmp) ?: '';
        @unlink($tmp);

        return $binary;
    }

    protected function buildSheetRows(array $rows, array $totals, array $visibleGroups): array
    {
        $headerTop = ['Datum'];
        $headerBottom = [''];
        $merges = ['A1:A2'];
        $currentColumn = 2;

        foreach ($this->exportGroups($visibleGroups) as $group) {
            $headerTop[] = $group['title'];
            $headerTop[] = '';
            $headerTop[] = '';
            $headerBottom[] = 'Excl.';
            $headerBottom[] = 'Btw';
            $headerBottom[] = 'Incl.';

            $startColumn = $currentColumn;
            $endColumn = $currentColumn + 2;
            $merges[] = $this->columnLetter($startColumn) . '1:' . $this->columnLetter($endColumn) . '1';
            $currentColumn += 3;
        }

        $sheetRows = [$headerTop, $headerBottom];

        foreach ($rows as $row) {
            $sheetRow = [$row['date']];

            foreach ($this->exportGroups($visibleGroups) as $group) {
                if ($group['type'] === 'total') {
                    $sheetRow[] = $row['total_excl_vat'];
                    $sheetRow[] = $row['total_vat'];
                    $sheetRow[] = $row['total_incl_vat'];
                    continue;
                }

                $rate = $group['rate'];
                $sheetRow[] = $row['vat_breakdown'][$rate]['excl'] ?? 0;
                $sheetRow[] = $row['vat_breakdown'][$rate]['vat'] ?? 0;
                $sheetRow[] = $row['vat_breakdown'][$rate]['incl'] ?? 0;
            }

            $sheetRows[] = $sheetRow;
        }

        $totalRow = ['Totaal'];

        foreach ($this->exportGroups($visibleGroups) as $group) {
            if ($group['type'] === 'total') {
                $totalRow[] = $totals['total_excl_vat'];
                $totalRow[] = $totals['total_vat'];
                $totalRow[] = $totals['total_incl_vat'];
                continue;
            }

            $rate = $group['rate'];
            $totalRow[] = $totals['vat_breakdown'][$rate]['excl'] ?? 0;
            $totalRow[] = $totals['vat_breakdown'][$rate]['vat'] ?? 0;
            $totalRow[] = $totals['vat_breakdown'][$rate]['incl'] ?? 0;
        }

        $sheetRows[] = $totalRow;

        return [$sheetRows, $merges, count($headerTop)];
    }

    protected function exportGroups(array $visibleGroups): array
    {
        $groups = [];

        if (! empty($visibleGroups['rate0'])) {
            $groups[] = ['type' => 'rate', 'rate' => '0', 'title' => '0%'];
        }

        if (! empty($visibleGroups['rate6'])) {
            $groups[] = ['type' => 'rate', 'rate' => '6', 'title' => '6%'];
        }

        if (! empty($visibleGroups['rate12'])) {
            $groups[] = ['type' => 'rate', 'rate' => '12', 'title' => '12%'];
        }

        if (! empty($visibleGroups['rate21'])) {
            $groups[] = ['type' => 'rate', 'rate' => '21', 'title' => '21%'];
        }

        if (! empty($visibleGroups['total'])) {
            $groups[] = ['type' => 'total', 'title' => 'Totaal'];
        }

        return $groups;
    }

    protected function buildWorksheetXml(array $rows, array $merges, int $columnCount): string
    {
        $xmlRows = [];
        $lastRowIndex = count($rows);

        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            $excelRow = $rowIndex + 1;
            $isHeader = $excelRow <= 2;
            $isTotal = $excelRow === $lastRowIndex;

            foreach ($row as $columnIndex => $value) {
                $cellRef = $this->columnLetter($columnIndex + 1) . $excelRow;
                $style = $this->resolveCellStyle($columnIndex, $isHeader, $isTotal);

                if ($columnIndex === 0 || $isHeader) {
                    $cells[] = '<c r="' . $cellRef . '" t="inlineStr" s="' . $style . '"><is><t>' . $this->xmlEscape((string) $value) . '</t></is></c>';
                    continue;
                }

                $cells[] = '<c r="' . $cellRef . '" s="' . $style . '"><v>' . $this->normalizeNumber($value) . '</v></c>';
            }

            $xmlRows[] = '<row r="' . $excelRow . '">' . implode('', $cells) . '</row>';
        }

        $colsXml = '<col min="1" max="1" width="14" customWidth="1"/>';

        if ($columnCount > 1) {
            $colsXml .= '<col min="2" max="' . $columnCount . '" width="14" customWidth="1"/>';
        }

        $mergeXml = '';
        if (! empty($merges)) {
            $mergeXml = '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $merge) {
                $mergeXml .= '<mergeCell ref="' . $merge . '"/>';
            }
            $mergeXml .= '</mergeCells>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<cols>' . $colsXml . '</cols>'
            . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
            . $mergeXml
            . '</worksheet>';
    }

    protected function resolveCellStyle(int $columnIndex, bool $isHeader, bool $isTotal): string
    {
        if ($isHeader) {
            return '1';
        }

        if ($columnIndex === 0) {
            return $isTotal ? '3' : '2';
        }

        return $isTotal ? '3' : '0';
    }

    protected function buildContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    protected function buildRootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    protected function buildWorkbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Dagtotalen" sheetId="1" r:id="rId1"/>'
            . '</sheets>'
            . '</workbook>';
    }

    protected function buildWorkbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    protected function buildStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="2">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '</fills>'
            . '<borders count="1">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '</cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="4" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1">'
            . '<cellStyle name="Normal" xfId="0" builtinId="0"/>'
            . '</cellStyles>'
            . '</styleSheet>';
    }

    protected function columnLetter(int $index): string
    {
        $letter = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = intdiv($index - $mod - 1, 26);
        }

        return $letter;
    }

    protected function normalizeNumber(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
