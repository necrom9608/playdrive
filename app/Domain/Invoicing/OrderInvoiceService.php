<?php

namespace App\Domain\Invoicing;

use App\Models\Order;
use App\Models\OrderInvoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Factuur van een afgerekende bestelling maken in WeFact.
 *
 *   1. klant zoeken of aanmaken (btw-nummer → Peppol, anders e-mail)
 *   2. factuur aanmaken met één lijn per orderlijn (+ korting per btw-tarief)
 *   3. definitief versturen: via Peppol als er een Belgisch btw-nummer is, anders per e-mail
 *   4. als betaald markeren (aan de kassa betaald), met de betaalwijze
 *
 * Elke stap wordt opgeslagen; "opnieuw" gaat verder waar het stopte (geen dubbele factuur).
 */
class OrderInvoiceService
{
    /** Standaard btw-codes van WeFact; per venue aan te passen in Tenantinstellingen. */
    public const DEFAULT_TAX_CODES = ['21' => 'V21', '12' => 'V12', '6' => 'V6', '0' => 'V0'];

    /** Betaalwijzen die (nog) niet betaald zijn: dan niet als betaald markeren. */
    private const UNPAID_METHODS = ['invoice', 'factuur', 'transfer', 'overschrijving', 'wire'];

    public static function configured(?Tenant $tenant): bool
    {
        return $tenant !== null && filled($tenant->wefact_api_key);
    }

    public static function taxCodes(Tenant $tenant): array
    {
        $custom = is_array($tenant->wefact_tax_codes) ? array_filter($tenant->wefact_tax_codes, 'filled') : [];

        return array_map('strval', $custom + self::DEFAULT_TAX_CODES);
    }

    /** Factuurgegevens van de reservatie (frontdesk → reservatie → factuur). */
    public static function detailsFromRegistration(Order $order): array
    {
        $r = $order->registration;

        return [
            'company_name' => $r?->invoice_company_name,
            'contact_name' => $r?->name,
            'vat_number'   => $r?->invoice_vat_number,
            'email'        => $r?->invoice_email ?: $r?->email,
            'address'      => $r?->invoice_address,
            'postal_code'  => $r?->invoice_postal_code,
            'city'         => $r?->invoice_city,
            'country'      => 'BE',
        ];
    }

    /**
     * Na het afrekenen (factuur gevraagd): factuur klaarzetten met de gegevens van de reservatie
     * en na het antwoord naar WeFact sturen. Ontbreken er gegevens, dan blijft ze klaarstaan in Verkopen.
     */
    public function afterCheckout(Order $order): void
    {
        if (! $order->invoice_requested || $order->status !== Order::STATUS_PAID) {
            return;
        }

        $tenant = Tenant::query()->find($order->tenant_id);
        if (! self::configured($tenant) || OrderInvoice::query()->where('order_id', $order->id)->exists()) {
            return;
        }

        $invoice = $this->prepare($order, self::detailsFromRegistration($order), null);

        if ($invoice->status === OrderInvoice::STATUS_PENDING) {
            $id = $invoice->id;
            dispatch(function () use ($id) {
                if ($invoice = OrderInvoice::query()->find($id)) {
                    app(OrderInvoiceService::class)->process($invoice);
                }
            })->afterResponse();
        }
    }

    /** Factuurgegevens opslaan (of bijwerken zolang er nog niets naar WeFact ging). */
    public function prepare(Order $order, array $details, ?int $actorId, bool $draft = false): OrderInvoice
    {
        $invoice = OrderInvoice::query()->firstOrNew(['order_id' => $order->id], [
            'tenant_id'  => $order->tenant_id,
            'created_by' => $actorId,
        ]);

        // Klant en factuur bestaan al in WeFact: gegevens niet meer wijzigen.
        if (! $invoice->wefact_invoice_id) {
            $clean = fn ($v) => filled($v) ? trim((string) $v) : null;
            $invoice->fill([
                'company_name' => $clean($details['company_name'] ?? null),
                'contact_name' => $clean($details['contact_name'] ?? null),
                'vat_number'   => self::normalizeVat($details['vat_number'] ?? null),
                'email'        => $clean($details['email'] ?? null) ? strtolower(trim($details['email'])) : null,
                'address'      => $clean($details['address'] ?? null),
                'postal_code'  => $clean($details['postal_code'] ?? null),
                'city'         => $clean($details['city'] ?? null),
                'country'      => strtoupper($clean($details['country'] ?? null) ?: 'BE'),
                'po_number'    => $clean($details['po_number'] ?? null),
                'comment'      => $clean($details['comment'] ?? null),
            ]);
            $invoice->channel = self::peppolId($invoice->vat_number) ? OrderInvoice::CHANNEL_PEPPOL : OrderInvoice::CHANNEL_EMAIL;
        }

        $missing = $this->missingDetails($invoice);
        $invoice->status = $missing
            ? OrderInvoice::STATUS_MISSING_DETAILS
            : ($draft && ! $invoice->wefact_invoice_id ? OrderInvoice::STATUS_DRAFT : OrderInvoice::STATUS_PENDING);
        $invoice->error  = $missing;
        $invoice->save();

        if (! $order->invoice_requested) {
            $order->forceFill(['invoice_requested' => true])->save();
        }

        return $invoice;
    }

    /**
     * Factuurgegevens aanpassen zonder te versturen (Verkopen → Factuurgegevens).
     * Ook op de reservatie bewaard, zodat een volgende bon van die reservatie dezelfde gegevens krijgt.
     */
    public function saveDetails(Order $order, array $details, bool $requested, ?int $actorId): ?OrderInvoice
    {
        $invoice = OrderInvoice::query()->where('order_id', $order->id)->first();

        if ($invoice?->wefact_invoice_id) {
            throw new RuntimeException('Deze factuur staat al in WeFact: pas de gegevens daar aan.');
        }

        if (! $requested) {
            $invoice?->delete();
            $order->forceFill(['invoice_requested' => false])->save();

            return null;
        }

        $invoice = $this->prepare($order, $details, $actorId, draft: true);

        if ($registration = $order->registration) {
            $registration->forceFill([
                'invoice_requested'    => true,
                'invoice_company_name' => $invoice->company_name,
                'invoice_vat_number'   => $invoice->vat_number,
                'invoice_email'        => $invoice->email,
                'invoice_address'      => $invoice->address,
                'invoice_postal_code'  => $invoice->postal_code,
                'invoice_city'         => $invoice->city,
            ])->save();
        }

        return $invoice;
    }

    /** Naar WeFact: verdergaan waar het de vorige keer stopte. */
    public function process(OrderInvoice $invoice, bool $forceEmail = false): OrderInvoice
    {
        $lock = Cache::lock("order-invoice-{$invoice->id}", 60);
        if (! $lock->get()) {
            throw new RuntimeException('Deze factuur wordt al verwerkt.');
        }

        try {
            $invoice->refresh();
            $order  = Order::query()->with(['items', 'registration'])->findOrFail($invoice->order_id);
            $tenant = Tenant::query()->findOrFail($invoice->tenant_id);

            if ($invoice->status === OrderInvoice::STATUS_DONE) {
                return $invoice;
            }
            if ($missing = $this->missingDetails($invoice)) {
                $invoice->update(['status' => OrderInvoice::STATUS_MISSING_DETAILS, 'error' => $missing]);

                return $invoice;
            }
            if (! self::configured($tenant)) {
                throw new RuntimeException('WeFact is niet ingesteld (Backoffice → Tenantinstellingen → WeFact).');
            }
            $this->assertInvoiceable($order);

            if ($forceEmail && ! $invoice->sent_at) {
                if (! $invoice->email) {
                    throw new RuntimeException('Vul een e-mailadres in om per e-mail te versturen.');
                }
                $invoice->channel = OrderInvoice::CHANNEL_EMAIL;
            }

            $api = new WeFactClient((string) $tenant->wefact_api_key);
            $invoice->increment('attempts');

            if (! $invoice->wefact_debtor_code) {
                $invoice->update(['wefact_debtor_code' => $this->debtor($api, $invoice)]);
            }

            if (! $invoice->wefact_invoice_id) {
                $created = $api->call('invoice', 'add', $this->invoicePayload($invoice, $order, $tenant))['invoice'] ?? [];
                $invoice->update([
                    'wefact_invoice_id'   => (int) ($created['Identifier'] ?? 0) ?: null,
                    'wefact_invoice_code' => $created['InvoiceCode'] ?? null,
                ]);
                if (! $invoice->wefact_invoice_id) {
                    throw new RuntimeException('WeFact gaf geen factuurnummer terug.');
                }
            }

            if (! $invoice->sent_at) {
                if ($forceEmail) {
                    // Factuur werd als Peppol aangemaakt: verzendmethode omzetten naar e-mail.
                    $api->call('invoice', 'edit', ['Identifier' => $invoice->wefact_invoice_id, 'InvoiceMethod' => 0]);
                }
                $sent = $api->call('invoice', 'sendbyemail', ['Identifier' => $invoice->wefact_invoice_id])['invoice'] ?? [];
                $invoice->update([
                    'sent_at'             => now(),
                    'wefact_invoice_code' => $sent['InvoiceCode'] ?? $invoice->wefact_invoice_code,
                ]);
            }

            if (! $invoice->paid_marked_at && ! in_array(strtolower((string) $order->payment_method), self::UNPAID_METHODS, true)) {
                $api->call('invoice', 'markaspaid', [
                    'Identifier'    => $invoice->wefact_invoice_id,
                    'PayDate'       => ($order->paid_at ?? now())->toDateString(),
                    'PaymentMethod' => self::paymentMethod($order->payment_method),
                ]);
                $invoice->update(['paid_marked_at' => now()]);
            }

            $invoice->update(['status' => OrderInvoice::STATUS_DONE, 'error' => null]);
            $order->forceFill(['invoice_requested' => true, 'invoice_exported_at' => now()])->save();
        } catch (Throwable $e) {
            Log::warning('WeFact-factuur mislukt', ['order_invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            $invoice->update(['status' => OrderInvoice::STATUS_ERROR, 'error' => mb_substr($e->getMessage(), 0, 1000)]);
        } finally {
            $lock->release();
        }

        return $invoice->fresh();
    }

    // ------------------------------------------------------------------

    private function missingDetails(OrderInvoice $invoice): ?string
    {
        if (! $invoice->company_name && ! $invoice->contact_name) {
            return 'Vul een bedrijfsnaam of naam in.';
        }
        if ($invoice->channel === OrderInvoice::CHANNEL_EMAIL && ! $invoice->email) {
            return $invoice->vat_number
                ? 'Geen Belgisch btw-nummer (Peppol): vul een e-mailadres in.'
                : 'Vul een e-mailadres of een Belgisch btw-nummer (Peppol) in.';
        }

        return null;
    }

    private function assertInvoiceable(Order $order): void
    {
        if ($order->status !== Order::STATUS_PAID || $order->cancelled_at) {
            throw new RuntimeException('Enkel afgerekende, niet-geannuleerde bestellingen kunnen gefactureerd worden.');
        }
        if ($order->refunded_at) {
            throw new RuntimeException('Deze bestelling werd terugbetaald: maak de factuur (of creditnota) in WeFact zelf.');
        }
        if ($order->items->isEmpty()) {
            throw new RuntimeException('Deze bestelling heeft geen lijnen.');
        }
    }

    /** Klant zoeken (btw-nummer, anders e-mail) of aanmaken; geeft de DebtorCode terug. */
    private function debtor(WeFactClient $api, OrderInvoice $invoice): string
    {
        $peppol = $invoice->channel === OrderInvoice::CHANNEL_PEPPOL ? self::peppolId($invoice->vat_number) : null;

        [$searchAt, $searchFor] = $invoice->vat_number
            ? ['TaxNumber', $invoice->vat_number]
            : ['EmailAddress', $invoice->email];

        $found = $api->call('debtor', 'list', ['searchat' => $searchAt, 'searchfor' => $searchFor, 'limit' => 5])['debtors'] ?? [];
        $match = collect($found)->first(fn ($d) => filled($d['DebtorCode'] ?? null));

        if ($match) {
            $edit = ['Identifier' => $match['Identifier'], 'InvoiceMethod' => $peppol ? 5 : 0];
            if ($peppol) {
                $edit['PeppolAddress'] = $peppol;
            }
            if ($invoice->email) {
                $edit['EmailAddress'] = $invoice->email;
            }
            $api->call('debtor', 'edit', $edit);

            return (string) $match['DebtorCode'];
        }

        [$initials, $surname] = self::splitName($invoice->contact_name);

        $created = $api->call('debtor', 'add', array_filter([
            'CompanyName'   => $invoice->company_name,
            'Initials'      => $initials,
            'SurName'       => $surname ?: ($invoice->company_name ? null : $invoice->contact_name),
            'TaxNumber'     => $invoice->vat_number,
            'Address'       => $invoice->address,
            'ZipCode'       => $invoice->postal_code,
            'City'          => $invoice->city,
            'Country'       => $invoice->country ?: 'BE',
            'EmailAddress'  => $invoice->email,
            'InvoiceMethod' => $peppol ? 5 : 0,
            'PeppolAddress' => $peppol,
        ], fn ($v) => $v !== null && $v !== ''))['debtor'] ?? [];

        $code = (string) ($created['DebtorCode'] ?? '');
        if ($code === '') {
            throw new RuntimeException('WeFact gaf geen klantnummer terug.');
        }

        return $code;
    }

    private function invoicePayload(OrderInvoice $invoice, Order $order, Tenant $tenant): array
    {
        $codes = self::taxCodes($tenant);
        $lines = [];
        $byRate = [];

        foreach ($order->items as $item) {
            $rate = (float) $item->vat_rate;
            $code = $codes[self::rateKey($rate)] ?? null;
            if (! $code) {
                throw new RuntimeException("Geen WeFact-btw-code voor {$rate}% (Tenantinstellingen → WeFact).");
            }

            $lines[] = [
                'Description'  => (string) $item->name,
                'Number'       => (int) $item->quantity,
                'PriceExcl'    => round((float) $item->unit_price_incl_vat / (1 + $rate / 100), 5),
                'TaxCode'      => $code,
            ];
            $byRate[self::rateKey($rate)] = ($byRate[self::rateKey($rate)] ?? 0) + (float) $item->line_total_incl_vat;
        }

        // Manuele korting: verdeeld over de btw-tarieven, naar verhouding van het bedrag.
        $discount = round((float) $order->manual_discount_amount, 2);
        $total = array_sum($byRate);
        if ($discount > 0 && $total > 0) {
            $label = 'Korting'.($order->manual_discount_label ? ': '.$order->manual_discount_label : '');
            $rest = $discount;
            $keys = array_keys($byRate);
            foreach ($keys as $i => $key) {
                $share = $i === count($keys) - 1 ? $rest : round($discount * $byRate[$key] / $total, 2);
                $rest = round($rest - $share, 2);
                if ($share <= 0) {
                    continue;
                }
                $lines[] = [
                    'Description' => $label,
                    'Number'      => 1,
                    'PriceExcl'   => -round($share / (1 + ((float) $key) / 100), 5),
                    'TaxCode'     => $codes[$key],
                ];
            }
        }

        // Extra commentaar: notitieregel onderaan de factuur (enkel een omschrijving).
        if ($invoice->comment) {
            $lines[] = ['Description' => $invoice->comment];
        }

        return [
            'DebtorCode'      => $invoice->wefact_debtor_code,
            'Date'            => ($order->paid_at ?? now())->toDateString(),
            'InvoiceMethod'   => $invoice->channel === OrderInvoice::CHANNEL_PEPPOL ? 5 : 0,
            'VatCalcMethod'   => 'incl',
            // Bestelbonnummer van de klant als referentie (gaat ook mee in Peppol), anders het bonnummer.
            'ReferenceNumber' => $invoice->po_number ?: 'Bon #'.$order->id,
            'Comment'         => 'PlayDrive bon #'.$order->id,
            'Description'     => $tenant->display_name.' · bon #'.$order->id,
            'PaymentMethod'   => self::paymentMethod($order->payment_method),
            'InvoiceLines'    => $lines,
        ];
    }

    // ------------------------------------------------------------------

    /** "be 0123.456.789" → "BE0123456789" */
    public static function normalizeVat(mixed $vat): ?string
    {
        $vat = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $vat));
        if ($vat === '') {
            return null;
        }
        // Enkel cijfers (10): Belgisch ondernemingsnummer
        if (ctype_digit($vat) && strlen($vat) === 10) {
            return 'BE'.$vat;
        }
        if (ctype_digit($vat) && strlen($vat) === 9) {
            return 'BE0'.$vat;
        }

        return $vat;
    }

    /** Peppol-adres voor een Belgisch btw-nummer: 0208:{ondernemingsnummer}. */
    public static function peppolId(?string $vat): ?string
    {
        return $vat && preg_match('/^BE([01]\d{9})$/', $vat, $m) ? '0208:'.$m[1] : null;
    }

    private static function rateKey(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }

    private static function paymentMethod(?string $method): string
    {
        return match (strtolower((string) $method)) {
            'cash', 'contant'                                => 'cash',
            'bancontact', 'card', 'pin', 'maestro', 'kaart'  => 'card',
            'voucher', 'mixed', 'split'                      => 'various',
            'invoice', 'factuur', 'transfer', 'overschrijving', 'wire' => 'wire',
            default                                          => 'other',
        };
    }

    /** "Jan Peeters" → ["Jan", "Peeters"] */
    private static function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        if (count($parts) < 2) {
            return [null, $parts[0] ?? null];
        }
        $first = array_shift($parts);

        return [$first, implode(' ', $parts)];
    }
}
