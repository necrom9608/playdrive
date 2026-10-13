<?php

namespace App\Domain\Accounting;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dagtotalen: één bron voor het backoffice-scherm, de Excel-export en de mail naar de boekhouding.
 *
 * - Btw-overzicht: som van de orderlijnen per dag en btw-tarief (zoals voorheen).
 * - Betaalwijzen: som van de effectief betaalde ordertotalen per dag en betaalwijze.
 *   Manuele kortingen zitten wel in het ordertotaal maar niet in de orderlijnen; daarom
 *   staat er een aansluiting onderaan het tweede tabblad.
 */
class DayTotalsReportService
{
    private const RATES = ['0', '6', '12', '21'];

    private const PAYMENT_LABELS = [
        'cash' => 'Cash',
        'bancontact' => 'Bancontact',
        'card' => 'Kaart',
        'transfer' => 'Overschrijving',
        'voucher' => 'Cadeaubon',
        'unknown' => 'Onbekend',
    ];

    public function build(int $tenantId, AccountingPeriod $period, bool $includeInvoices = false): array
    {
        $rows = $this->buildVatRows($tenantId, $period, $includeInvoices);
        $totals = $this->buildVatTotals($rows);
        $payments = $this->buildPayments($tenantId, $period, $includeInvoices);

        return [
            'period' => $period->toArray(),
            'include_invoices' => $includeInvoices,
            'rows' => $rows,
            'totals' => $totals,
            'payments' => $payments,
            'order_count' => $payments['totals']['orders'],
        ];
    }

    // ---------------------------------------------------------------------------------
    // Btw-overzicht
    // ---------------------------------------------------------------------------------

    protected function buildVatRows(int $tenantId, AccountingPeriod $period, bool $includeInvoices): array
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
            ->whereBetween('orders.paid_at', [$period->start, $period->end])
            ->where('orders.status', Order::STATUS_PAID);

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
            $date = (string) $item->order_date;
            $vatRate = (string) ((int) $item->vat_rate_rounded);

            if (! isset($grouped[$date])) {
                $grouped[$date] = [
                    'date' => $date,
                    'total_excl_vat' => 0,
                    'total_vat' => 0,
                    'total_incl_vat' => 0,
                    'vat_breakdown' => $this->emptyBreakdown(),
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

        return array_values(array_map(fn (array $row) => $this->roundVatRow($row), $grouped));
    }

    protected function buildVatTotals(array $rows): array
    {
        $totals = [
            'total_excl_vat' => 0,
            'total_vat' => 0,
            'total_incl_vat' => 0,
            'vat_breakdown' => $this->emptyBreakdown(),
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

        return $this->roundVatRow($totals);
    }

    protected function emptyBreakdown(): array
    {
        $breakdown = [];

        foreach (self::RATES as $rate) {
            $breakdown[$rate] = ['excl' => 0, 'vat' => 0, 'incl' => 0];
        }

        return $breakdown;
    }

    protected function roundVatRow(array $row): array
    {
        $row['total_excl_vat'] = round((float) $row['total_excl_vat'], 2);
        $row['total_vat'] = round((float) $row['total_vat'], 2);
        $row['total_incl_vat'] = round((float) $row['total_incl_vat'], 2);

        foreach ($row['vat_breakdown'] as $rate => $values) {
            $row['vat_breakdown'][$rate] = [
                'excl' => round((float) $values['excl'], 2),
                'vat' => round((float) $values['vat'], 2),
                'incl' => round((float) $values['incl'], 2),
            ];
        }

        return $row;
    }

    // ---------------------------------------------------------------------------------
    // Betaalwijzen
    // ---------------------------------------------------------------------------------

    protected function buildPayments(int $tenantId, AccountingPeriod $period, bool $includeInvoices): array
    {
        $query = Order::query()
            ->selectRaw('DATE(paid_at) as order_date')
            ->selectRaw("COALESCE(NULLIF(payment_method, ''), 'unknown') as method")
            ->selectRaw('SUM(total_incl_vat) as total')
            ->selectRaw('SUM(manual_discount_amount) as discount')
            ->selectRaw('COUNT(*) as orders')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$period->start, $period->end])
            ->where('status', Order::STATUS_PAID);

        if (! $includeInvoices) {
            $query->where('invoice_requested', false);
        }

        $records = $query
            ->groupBy(DB::raw('DATE(paid_at)'), DB::raw("COALESCE(NULLIF(payment_method, ''), 'unknown')"))
            ->orderBy('order_date')
            ->get();

        $methodKeys = [];
        $grouped = [];

        foreach ($records as $record) {
            $date = (string) $record->order_date;
            $method = strtolower((string) $record->method);
            $methodKeys[$method] = true;

            $grouped[$date] ??= ['date' => $date, 'methods' => [], 'total' => 0, 'discount' => 0, 'orders' => 0];
            $grouped[$date]['methods'][$method] = ($grouped[$date]['methods'][$method] ?? 0) + (float) $record->total;
            $grouped[$date]['total'] += (float) $record->total;
            $grouped[$date]['discount'] += (float) $record->discount;
            $grouped[$date]['orders'] += (int) $record->orders;
        }

        // Vaste volgorde: bekende betaalwijzen eerst, de rest alfabetisch
        $known = array_keys(self::PAYMENT_LABELS);
        $keys = array_keys($methodKeys);
        usort($keys, function ($a, $b) use ($known) {
            $ia = array_search($a, $known, true);
            $ib = array_search($b, $known, true);
            $ia = $ia === false ? 100 : $ia;
            $ib = $ib === false ? 100 : $ib;

            return $ia === $ib ? strcmp($a, $b) : $ia <=> $ib;
        });

        $methods = array_map(fn ($key) => ['key' => $key, 'label' => $this->paymentLabel($key)], $keys);

        $totals = ['methods' => array_fill_keys($keys, 0), 'total' => 0, 'discount' => 0, 'orders' => 0];
        $rows = [];

        foreach ($grouped as $row) {
            $normalized = ['date' => $row['date'], 'methods' => [], 'total' => 0, 'discount' => 0, 'orders' => $row['orders']];

            foreach ($keys as $key) {
                $amount = round((float) ($row['methods'][$key] ?? 0), 2);
                $normalized['methods'][$key] = $amount;
                $totals['methods'][$key] += $amount;
            }

            $normalized['total'] = round($row['total'], 2);
            $normalized['discount'] = round($row['discount'], 2);

            $totals['total'] += $normalized['total'];
            $totals['discount'] += $normalized['discount'];
            $totals['orders'] += $row['orders'];

            $rows[] = $normalized;
        }

        foreach ($totals['methods'] as $key => $amount) {
            $totals['methods'][$key] = round($amount, 2);
        }

        $totals['total'] = round($totals['total'], 2);
        $totals['discount'] = round($totals['discount'], 2);

        return [
            'methods' => $methods,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    public function paymentLabel(string $key): string
    {
        return self::PAYMENT_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    // ---------------------------------------------------------------------------------
    // Excel
    // ---------------------------------------------------------------------------------

    /**
     * @param array $visibleGroups ['rate0' => bool, 'rate6' => bool, 'rate12' => bool, 'rate21' => bool, 'total' => bool]
     */
    public function buildXlsx(array $report, array $visibleGroups, bool $includePayments = true): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is niet beschikbaar op deze server.');
        }

        $sheets = [$this->buildVatSheet($report, $visibleGroups)];

        if ($includePayments) {
            $sheets[] = $this->buildPaymentSheet($report);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'daytotals_xlsx_');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml(count($sheets)));
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml(count($sheets)));
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach ($sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', $this->worksheetXml($sheet));
        }

        $zip->close();

        $binary = file_get_contents($tmp) ?: '';
        @unlink($tmp);

        return $binary;
    }

    protected function buildVatSheet(array $report, array $visibleGroups): array
    {
        $groups = $this->exportGroups($visibleGroups);

        $headerTop = ['Datum'];
        $headerBottom = [''];
        $merges = ['A1:A2'];
        $column = 2;

        foreach ($groups as $group) {
            array_push($headerTop, $group['title'], '', '');
            array_push($headerBottom, 'Excl.', 'Btw', 'Incl.');
            $merges[] = $this->columnLetter($column) . '1:' . $this->columnLetter($column + 2) . '1';
            $column += 3;
        }

        $rows = [$headerTop, $headerBottom];

        foreach ($report['rows'] as $row) {
            $rows[] = array_merge([$this->formatDate($row['date'])], $this->vatValues($row, $groups));
        }

        $rows[] = array_merge(['Totaal'], $this->vatValues($report['totals'], $groups));

        return [
            'name' => 'Dagtotalen',
            'rows' => $rows,
            'merges' => $merges,
            'columns' => count($headerTop),
            'header_rows' => 2,
            'bold_rows' => [count($rows)],
        ];
    }

    protected function vatValues(array $row, array $groups): array
    {
        $values = [];

        foreach ($groups as $group) {
            if ($group['type'] === 'total') {
                array_push($values, $row['total_excl_vat'], $row['total_vat'], $row['total_incl_vat']);
                continue;
            }

            $rate = $row['vat_breakdown'][$group['rate']] ?? ['excl' => 0, 'vat' => 0, 'incl' => 0];
            array_push($values, $rate['excl'], $rate['vat'], $rate['incl']);
        }

        return $values;
    }

    protected function buildPaymentSheet(array $report): array
    {
        $payments = $report['payments'];
        $methods = $payments['methods'];

        $header = ['Datum'];
        foreach ($methods as $method) {
            $header[] = $method['label'];
        }
        array_push($header, 'Totaal ontvangen', 'Korting', 'Aantal');

        $rows = [$header];

        foreach ($payments['rows'] as $row) {
            $line = [$this->formatDate($row['date'])];
            foreach ($methods as $method) {
                $line[] = $row['methods'][$method['key']] ?? 0;
            }
            array_push($line, $row['total'], $row['discount'], ['int' => $row['orders']]);
            $rows[] = $line;
        }

        $totalLine = ['Totaal'];
        foreach ($methods as $method) {
            $totalLine[] = $payments['totals']['methods'][$method['key']] ?? 0;
        }
        array_push($totalLine, $payments['totals']['total'], $payments['totals']['discount'], ['int' => $payments['totals']['orders']]);
        $rows[] = $totalLine;
        $boldRows = [count($rows)];

        // Aansluiting btw-overzicht ↔ ontvangen bedragen
        $vatIncl = (float) $report['totals']['total_incl_vat'];
        $discount = (float) $payments['totals']['discount'];
        $received = (float) $payments['totals']['total'];
        $difference = round($received - ($vatIncl - $discount), 2);

        $rows[] = [];
        $rows[] = ['Aansluiting'];
        $boldRows[] = count($rows);
        $rows[] = ['Omzet volgens btw-overzicht (incl.)', $vatIncl];
        $rows[] = ['Manuele kortingen', -$discount];
        $rows[] = ['Ontvangen volgens betaalwijzen', $received];

        if (abs($difference) >= 0.01) {
            $rows[] = ['Verschil', $difference];
        }

        return [
            'name' => 'Betaalwijzen',
            'rows' => $rows,
            'merges' => [],
            'columns' => max(count($header), 2),
            'header_rows' => 1,
            'bold_rows' => $boldRows,
            'first_column_width' => 34,
        ];
    }

    protected function exportGroups(array $visibleGroups): array
    {
        $groups = [];

        foreach (self::RATES as $rate) {
            if (! empty($visibleGroups['rate' . $rate])) {
                $groups[] = ['type' => 'rate', 'rate' => $rate, 'title' => $rate . '%'];
            }
        }

        if (! empty($visibleGroups['total'])) {
            $groups[] = ['type' => 'total', 'title' => 'Totaal'];
        }

        return $groups;
    }

    protected function worksheetXml(array $sheet): string
    {
        $xmlRows = [];

        foreach ($sheet['rows'] as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $isHeader = $excelRow <= $sheet['header_rows'];
            $isBold = in_array($excelRow, $sheet['bold_rows'], true);
            $cells = [];

            foreach ($row as $columnIndex => $value) {
                $ref = $this->columnLetter($columnIndex + 1) . $excelRow;

                if (is_array($value) && array_key_exists('int', $value)) {
                    $style = $isBold ? '5' : '4';
                    $cells[] = '<c r="' . $ref . '" s="' . $style . '"><v>' . (int) $value['int'] . '</v></c>';
                    continue;
                }

                if ($columnIndex === 0 || $isHeader || ! is_numeric($value)) {
                    $style = ($isHeader || $isBold) ? '1' : '2';
                    $cells[] = '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t>' . $this->xmlEscape((string) $value) . '</t></is></c>';
                    continue;
                }

                $style = $isBold ? '3' : '0';
                $cells[] = '<c r="' . $ref . '" s="' . $style . '"><v>' . number_format((float) $value, 2, '.', '') . '</v></c>';
            }

            $xmlRows[] = '<row r="' . $excelRow . '">' . implode('', $cells) . '</row>';
        }

        $firstWidth = $sheet['first_column_width'] ?? 14;
        $cols = '<col min="1" max="1" width="' . $firstWidth . '" customWidth="1"/>';

        if ($sheet['columns'] > 1) {
            $cols .= '<col min="2" max="' . $sheet['columns'] . '" width="15" customWidth="1"/>';
        }

        $mergeXml = '';
        if (! empty($sheet['merges'])) {
            $mergeXml = '<mergeCells count="' . count($sheet['merges']) . '">';
            foreach ($sheet['merges'] as $merge) {
                $mergeXml .= '<mergeCell ref="' . $merge . '"/>';
            }
            $mergeXml .= '</mergeCells>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
            . $mergeXml
            . '</worksheet>';
    }

    protected function contentTypesXml(int $sheetCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $overrides
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    protected function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    protected function workbookXml(array $sheets): string
    {
        $xml = '';
        foreach ($sheets as $index => $sheet) {
            $id = $index + 1;
            $xml .= '<sheet name="' . $this->xmlEscape($sheet['name']) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $xml . '</sheets>'
            . '</workbook>';
    }

    protected function workbookRelationshipsXml(int $sheetCount): string
    {
        $xml = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }

        $xml .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $xml
            . '</Relationships>';
    }

    protected function stylesXml(): string
    {
        // 0 = bedrag, 1 = vet tekst, 2 = tekst, 3 = vet bedrag, 4 = geheel getal, 5 = vet geheel getal
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
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="4" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/>'
            . '<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="1" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
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

    protected function formatDate(string $date): string
    {
        $parts = explode('-', $date);

        return count($parts) === 3 ? $parts[2] . '/' . $parts[1] . '/' . $parts[0] : $date;
    }

    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
