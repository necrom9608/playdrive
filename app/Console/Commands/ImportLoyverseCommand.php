<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLoyverseCommand extends Command
{
    protected $signature = 'playdrive:import-loyverse
        {receipts : Pad naar receipts CSV}
        {items : Pad naar receipts-by-item CSV}
        {--tenant=1 : Tenant ID}
        {--dry-run : Alleen simuleren, niets opslaan}';

    protected $description = 'Importeer Loyverse receipts en receipt-items naar Playdrive';

    protected array $categoryVatMap = [
        'inkom' => 6.00,
        'drank' => 21.00,
        'snacks' => 21.00,
    ];

    protected array $legacyProductAliases = [
        // Toegang
        'student dag' => 'inkom kind / student dagticket',
        'student 2u' => 'inkom kind / student 2u',
        'student 1u' => 'inkom kind / student 1u',
        'volwassenen dag' => 'inkom volwassene dagticket',
        'volwassenen 2u' => 'inkom volwassene 2u',
        'volwassenen 1u' => 'inkom volwassene 1u',

        // Abonnementen
        'abonnement student' => 'abonnement kind / student',
        'abonnement volwassenen' => 'abonnement volwassene',
        'abonnement volwassene' => 'abonnement volwassene',
        'abonnement kind student' => 'abonnement kind / student',

        // Drank
        'coca cola' => 'coca-cola',
        'coca cola zero' => 'coca-cola zero',
        'fanta' => 'fanta',
        'sprite' => 'sprite',
        'red bull' => 'red bull',
        'lipton ice tea' => 'fusetea peach',
        'lipton ice-tea' => 'fusetea peach',
        'thee' => 'tea',
        'tea' => 'tea',
        'koffie decafe' => 'koffie décafe',

        // Alcohol
        'witte wijn flesje' => 'witte wijn',
        'rode wijn santa tierra carmenere' => 'rode wijn',
        'cava gran baron brut' => 'cava',
        'lefort trippel' => null,

        // Snacks
        'soep' => 'tomatensoep',
        'popcorn large' => 'popcorn',

        // Legacy / geen goede match
        'verjaardagdsformule' => null,
        'verjaardag' => null,
        'mario kart tornooi' => null,
        'mario kart tornooi korting' => null,
        'cadeaubon' => null,
        'opleg' => null,
        'sleutelhanger' => null,
        'fanta lemon' => 'eaumega citroen',


    ];

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');

        $receiptsPath = $this->argument('receipts');
        $itemsPath = $this->argument('items');

        if (! is_file($receiptsPath)) {
            $this->error("Receipts bestand niet gevonden: {$receiptsPath}");
            return self::FAILURE;
        }

        if (! is_file($itemsPath)) {
            $this->error("Items bestand niet gevonden: {$itemsPath}");
            return self::FAILURE;
        }

        $receipts = $this->readCsvAssoc($receiptsPath);
        $items = $this->readCsvAssoc($itemsPath);

        if ($receipts->isEmpty()) {
            $this->error('Receipts CSV is leeg.');
            return self::FAILURE;
        }

        if ($items->isEmpty()) {
            $this->error('Items CSV is leeg.');
            return self::FAILURE;
        }

        $itemsByReceipt = $items->groupBy(fn (array $row) => trim((string) ($row['Bon nummer'] ?? '')));

        $productMaps = $this->buildProductMaps($tenantId);

        $stats = [
            'orders_created' => 0,
            'orders_updated' => 0,
            'items_created' => 0,
            'items_matched' => 0,
            'items_unmatched' => 0,
            'refund_orders' => 0,
            'cancelled_orders' => 0,
        ];

        $unmatched = [];

        $runner = function () use (
            $tenantId,
            $receipts,
            $itemsByReceipt,
            $productMaps,
            &$stats,
            &$unmatched
        ) {
            $bar = $this->output->createProgressBar($receipts->count());
            $bar->start();

            foreach ($receipts as $receiptRow) {
                $receiptNumber = trim((string) ($receiptRow['Bon nummer'] ?? ''));

                if ($receiptNumber === '') {
                    $bar->advance();
                    continue;
                }

                $receiptItems = $itemsByReceipt->get($receiptNumber, collect());

                [$order, $wasRecentlyCreated] = $this->upsertOrder($tenantId, $receiptRow);

                if ($wasRecentlyCreated) {
                    $stats['orders_created']++;
                } else {
                    $stats['orders_updated']++;
                }

                $order->items()->delete();

                $sortOrder = 1;

                foreach ($receiptItems as $itemRow) {
                    $match = $this->findMatchingProduct(
                        $productMaps,
                        (string) ($itemRow['Artikel'] ?? ''),
                        (string) ($itemRow['Categorie'] ?? '')
                    );

                    if ($match) {
                        $stats['items_matched']++;
                    } else {
                        $stats['items_unmatched']++;
                        $unmatchedKey = trim((string) ($itemRow['Categorie'] ?? '')) . ' :: ' . trim((string) ($itemRow['Artikel'] ?? ''));
                        $unmatched[$unmatchedKey] = ($unmatched[$unmatchedKey] ?? 0) + 1;
                    }

                    $rawQuantity = $this->toInt($itemRow['Aantal'] ?? 1);
                    $vatRate = $this->determineVatRate($itemRow);
                    $lineTotalIncl = $this->toMoney($itemRow['Bruto-omzet'] ?? 0);
                    $unitPriceIncl = $this->calculateUnitPriceInclVat($itemRow, $rawQuantity);

                    $orderItem = new OrderItem();
                    $orderItem->order_id = $order->id;
                    $orderItem->product_id = $match?->id;
                    $orderItem->name = trim((string) ($itemRow['Artikel'] ?? 'Onbekend artikel'));
                    $orderItem->quantity = $rawQuantity;
                    $orderItem->vat_rate = $vatRate;
                    $orderItem->unit_price_incl_vat = abs($unitPriceIncl);
                    $orderItem->unit_price_excl_vat = abs($this->calculateExclVat($unitPriceIncl, (float) $vatRate));
                    $orderItem->line_total_incl_vat = $lineTotalIncl;
                    $orderItem->line_subtotal_excl_vat = $this->calculateExclVat($lineTotalIncl, (float) $vatRate);
                    $orderItem->line_vat = $this->toMoney(
                        $orderItem->line_total_incl_vat - $orderItem->line_subtotal_excl_vat
                    );
                    $orderItem->sort_order = $sortOrder++;
                    $orderItem->source = 'loyverse';
                    $orderItem->source_reference = $this->buildItemSourceReference($itemRow);
                    $orderItem->legacy_category = $this->normalizeNullableString($itemRow['Categorie'] ?? null);
                    $orderItem->legacy_article_number = $this->normalizeNullableString($itemRow['Art.nr.'] ?? null);
                    $orderItem->save();

                    $stats['items_created']++;
                }

                if ($order->refund_amount && $order->refund_amount > 0) {
                    $stats['refund_orders']++;
                }

                if ($order->status === Order::STATUS_CANCELLED) {
                    $stats['cancelled_orders']++;
                }

                $bar->advance();
            }

            $bar->finish();
        };

        if ($dryRun) {
            DB::beginTransaction();

            try {
                $runner();
                DB::rollBack();
                $this->newLine(2);
                $this->warn('Dry run voltooid. Er werd niets opgeslagen.');
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } else {
            $runner();
            $this->newLine(2);
            $this->info('Import voltooid.');
        }

        $this->table(
            ['Metric', 'Waarde'],
            [
                ['Orders aangemaakt', $stats['orders_created']],
                ['Orders bijgewerkt', $stats['orders_updated']],
                ['Items aangemaakt', $stats['items_created']],
                ['Items gematcht met product', $stats['items_matched']],
                ['Items zonder productmatch', $stats['items_unmatched']],
                ['Refund orders', $stats['refund_orders']],
                ['Cancelled orders', $stats['cancelled_orders']],
            ]
        );

        if (! empty($unmatched)) {
            arsort($unmatched);

            $this->newLine();
            $this->warn('Niet-gematchte items:');

            $rows = [];
            foreach ($unmatched as $label => $count) {
                $rows[] = [$label, $count];
            }

            $this->table(['Categorie :: Artikel', 'Aantal lijnen'], array_slice($rows, 0, 100));
        }

        return self::SUCCESS;
    }

    protected function upsertOrder(int $tenantId, array $row): array
    {
        $receiptNumber = trim((string) ($row['Bon nummer'] ?? ''));
        $status = $this->mapOrderStatus($row);
        $paidAt = $this->parseDate($row['Datum'] ?? null);
        $paymentMethod = $this->mapPaymentMethod($row['Betaalwijzen'] ?? null);
        $grossTotal = $this->toMoney($row['Bruto-omzet'] ?? 0);
        $vatTotal = $this->toMoney($row['Btw'] ?? 0);
        $subtotal = $this->toMoney($grossTotal - $vatTotal);
        $isRefund = Str::lower(trim((string) ($row['Bon soort'] ?? ''))) === 'restitutie';

        $order = Order::query()->firstOrNew([
            'tenant_id' => $tenantId,
            'source' => 'loyverse',
            'source_reference' => $receiptNumber,
        ]);

        $wasRecentlyCreated = ! $order->exists;

        $order->tenant_id = $tenantId;
        $order->registration_id = null;
        $order->status = $status;
        $order->source = 'loyverse';
        $order->source_reference = $receiptNumber;
        $order->subtotal_excl_vat = $subtotal;
        $order->total_vat = $vatTotal;
        $order->total_incl_vat = $grossTotal;
        $order->payment_method = $paymentMethod;
        $order->invoice_requested = false;
        $order->paid_at = $status === Order::STATUS_PAID ? $paidAt : null;
        $order->cancelled_at = $status === Order::STATUS_CANCELLED ? $paidAt : null;
        $order->refund_amount = $isRefund ? abs($grossTotal) : null;
        $order->refund_method = $isRefund ? $paymentMethod : null;
        $order->refunded_at = $isRefund ? $paidAt : null;
        $order->refund_reason = $isRefund ? 'Loyverse legacy import' : null;
        $order->notes = $this->buildOrderNotes($row);
        $order->save();

        return [$order, $wasRecentlyCreated];
    }

    protected function buildOrderNotes(array $row): string
    {
        $parts = [
            'Loyverse import',
            'Bon: ' . trim((string) ($row['Bon nummer'] ?? '')),
            'Type: ' . trim((string) ($row['Bon soort'] ?? '')),
            'POS: ' . trim((string) ($row['POS'] ?? '')),
            'Winkel: ' . trim((string) ($row['Winkel'] ?? '')),
            'Medewerker: ' . trim((string) ($row['Naam medewerker'] ?? '')),
        ];

        if (! empty($row['Omschrijving'])) {
            $parts[] = 'Omschrijving: ' . trim((string) $row['Omschrijving']);
        }

        return implode(' | ', array_filter($parts));
    }

    protected function buildItemSourceReference(array $row): string
    {
        return implode('|', [
            trim((string) ($row['Bon nummer'] ?? '')),
            trim((string) ($row['Art.nr.'] ?? '')),
            trim((string) ($row['Artikel'] ?? '')),
        ]);
    }

    protected function buildProductMaps(int $tenantId): array
    {
        $products = Product::query()
            ->with('category')
            ->where('tenant_id', $tenantId)
            ->get();

        $byName = [];
        $byCategoryAndName = [];

        foreach ($products as $product) {
            $nameKey = $this->normalizeKey($product->name);
            $byName[$nameKey] ??= $product;

            $categoryName = $product->category?->name ?? '';
            $catKey = $this->normalizeKey($categoryName) . '|' . $nameKey;
            $byCategoryAndName[$catKey] ??= $product;
        }

        return [
            'by_name' => $byName,
            'by_category_and_name' => $byCategoryAndName,
        ];
    }

    protected function findMatchingProduct(array $maps, string $name, string $category): ?Product
    {
        $nameKey = $this->normalizeKey($name);
        $categoryKey = $this->normalizeKey($category);

        if (array_key_exists($nameKey, $this->legacyProductAliases)) {
            $alias = $this->legacyProductAliases[$nameKey];

            if ($alias === null) {
                return null;
            }

            $aliasedNameKey = $this->normalizeKey($alias);
            $aliasedCategoryAndName = $categoryKey . '|' . $aliasedNameKey;

            if (isset($maps['by_category_and_name'][$aliasedCategoryAndName])) {
                return $maps['by_category_and_name'][$aliasedCategoryAndName];
            }

            if (isset($maps['by_name'][$aliasedNameKey])) {
                return $maps['by_name'][$aliasedNameKey];
            }
        }

        $categoryAndName = $categoryKey . '|' . $nameKey;

        if (isset($maps['by_category_and_name'][$categoryAndName])) {
            return $maps['by_category_and_name'][$categoryAndName];
        }

        if (isset($maps['by_name'][$nameKey])) {
            return $maps['by_name'][$nameKey];
        }

        return null;
    }

    protected function mapOrderStatus(array $row): string
    {
        $status = Str::lower(trim((string) ($row['Status'] ?? '')));
        $type = Str::lower(trim((string) ($row['Bon soort'] ?? '')));

        if ($status === 'geannuleerd') {
            return Order::STATUS_CANCELLED;
        }

        if ($type === 'restitutie') {
            return Order::STATUS_PAID;
        }

        return Order::STATUS_PAID;
    }

    protected function mapPaymentMethod(?string $value): ?string
    {
        $value = Str::lower(trim((string) $value));

        return match ($value) {
            'cash', 'contant' => 'cash',
            'card', 'kaart', 'bancontact' => 'card',
            default => $value !== '' ? Str::slug($value, '_') : null,
        };
    }

    protected function determineVatRate(array $row): float
    {
        $categoryKey = $this->normalizeKey((string) ($row['Categorie'] ?? ''));

        if (isset($this->categoryVatMap[$categoryKey])) {
            return $this->categoryVatMap[$categoryKey];
        }

        $gross = $this->toMoney($row['Bruto-omzet'] ?? 0);
        $vat = $this->toMoney($row['Btw'] ?? 0);

        if ($gross == 0 || $vat == 0) {
            return 0.00;
        }

        $rate = ($vat / max(abs($gross - $vat), 0.01)) * 100;

        if (abs($rate - 6) < 1.5) {
            return 6.00;
        }

        if (abs($rate - 12) < 2) {
            return 12.00;
        }

        if (abs($rate - 21) < 2.5) {
            return 21.00;
        }

        return round($rate, 2);
    }

    protected function calculateUnitPriceInclVat(array $row, ?int $rawQuantity = null): float
    {
        $rawQuantity ??= $this->toInt($row['Aantal'] ?? 1);
        $qty = max(abs($rawQuantity), 1);
        $gross = $this->toMoney($row['Bruto-omzet'] ?? 0);

        return $this->toMoney($gross / $qty);
    }

    protected function calculateExclVat(float $incl, float $vatRate): float
    {
        if ($vatRate <= 0) {
            return $this->toMoney($incl);
        }

        return $this->toMoney($incl / (1 + ($vatRate / 100)));
    }

    protected function parseDate($value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Carbon::createFromFormat('d-m-Y H:i', $value, config('app.timezone'));
    }

    protected function toMoney($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $value = trim((string) $value);
        $value = str_replace(['€', ' '], '', $value);

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return round((float) $value, 2);
    }

    protected function toInt($value): int
    {
        return (int) round((float) str_replace(',', '.', (string) $value));
    }

    protected function normalizeKey(?string $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->ascii()
            ->replace('&', 'en')
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->trim()
            ->value();
    }

    protected function normalizeNullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    protected function readCsvAssoc(string $path): Collection
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new \RuntimeException("Kon CSV niet openen: {$path}");
        }

        $rows = collect();

        $headers = fgetcsv($handle, 0, ',');

        if (! $headers) {
            fclose($handle);
            return $rows;
        }

        $headers = array_map(function ($header) {
            $header = (string) $header;
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
            return trim($header);
        }, $headers);

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $data[$index] ?? null;
            }

            $rows->push($row);
        }

        fclose($handle);

        return $rows;
    }
}
