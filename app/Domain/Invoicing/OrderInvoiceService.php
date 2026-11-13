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

    /** Betaalwijze aan de kassa: bon afgesloten, klant (firma) betaalt de factuur achteraf. */
    public const METHOD_ON_INVOICE = 'invoice';

    /** Betaalstatussen (bron: WeFact). */
    public const PAY_OPEN      = 'open';
    public const PAY_OVERDUE   = 'overdue';
    public const PAY_PARTIAL   = 'partial';
    public const PAY_PAID      = 'paid';
    public const PAY_CREDITED  = 'credited';
    public const PAY_CANCELLED = 'cancelled';

    /** Nog te betalen: deze worden elk uur bij WeFact nagekeken. */
    public const PAY_UNSETTLED = [self::PAY_OPEN, self::PAY_OVERDUE, self::PAY_PARTIAL];

    public static function isUnpaidMethod(?string $method): bool
    {
        return in_array(strtolower((string) $method), self::UNPAID_METHODS, true);
    }

    public static function paymentTerm(Tenant $tenant): int
    {
        return max(0, (int) ($tenant->wefact_payment_term ?? 14)) ?: 14;
    }

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

            $unpaid = self::isUnpaidMethod($order->payment_method);

            if (! $invoice->paid_marked_at && ! $unpaid) {
                $api->call('invoice', 'markaspaid', [
                    'Identifier'    => $invoice->wefact_invoice_id,
                    'PayDate'       => ($order->paid_at ?? now())->toDateString(),
                    'PaymentMethod' => self::paymentMethod($order->payment_method),
                ]);
                $invoice->update(['paid_marked_at' => now(), 'payment_status' => self::PAY_PAID, 'amount_outstanding' => 0]);
            }

            $invoice->update(['status' => OrderInvoice::STATUS_DONE, 'error' => null]);

            // Achteraf te betalen: vervaldatum en openstaand bedrag meteen uit WeFact halen.
            if ($unpaid) {
                $invoice->update([
                    'payment_status'     => $invoice->payment_status ?? self::PAY_OPEN,
                    'amount_outstanding' => $invoice->amount_outstanding ?? $order->total_incl_vat,
                    'due_date'           => $invoice->due_date ?? ($order->paid_at ?? now())->copy()->addDays(self::paymentTerm($tenant)),
                ]);
                try {
                    $this->applyStatus($invoice, $api->call('invoice', 'show', ['Identifier' => $invoice->wefact_invoice_id])['invoice'] ?? []);
                } catch (Throwable) {
                    // de uurlijkse controle haalt het later op
                }
            }
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

    /** Betaalstatus van één factuur ophalen bij WeFact. */
    public function sync(OrderInvoice $invoice): OrderInvoice
    {
        $tenant = Tenant::query()->find($invoice->tenant_id);
        if (! $invoice->wefact_invoice_id || ! self::configured($tenant)) {
            throw new RuntimeException('Deze factuur staat (nog) niet in WeFact.');
        }

        $data = (new WeFactClient((string) $tenant->wefact_api_key))
            ->call('invoice', 'show', ['Identifier' => $invoice->wefact_invoice_id])['invoice'] ?? [];

        return $this->applyStatus($invoice, $data);
    }

    /** Betalingsherinnering per e-mail via WeFact. */
    public function sendReminder(OrderInvoice $invoice): OrderInvoice
    {
        $tenant = Tenant::query()->find($invoice->tenant_id);
        if (! $invoice->wefact_invoice_id || ! self::configured($tenant)) {
            throw new RuntimeException('Deze factuur staat (nog) niet in WeFact.');
        }
        if (! in_array($invoice->payment_status, self::PAY_UNSETTLED, true)) {
            throw new RuntimeException('Deze factuur staat niet meer open.');
        }

        $api = new WeFactClient((string) $tenant->wefact_api_key);
        $api->call('invoice', 'sendreminderbyemail', ['Identifier' => $invoice->wefact_invoice_id]);
        $invoice->update(['reminders_count' => $invoice->reminders_count + 1, 'last_reminder_at' => now()]);

        try {
            return $this->applyStatus($invoice, $api->call('invoice', 'show', ['Identifier' => $invoice->wefact_invoice_id])['invoice'] ?? []);
        } catch (Throwable) {
            return $invoice->fresh();
        }
    }

    /** Alle openstaande facturen nakijken (planner, elk uur). @return int aantal nagekeken */
    public function syncUnsettled(): int
    {
        $count = 0;
        OrderInvoice::query()
            ->where('status', OrderInvoice::STATUS_DONE)
            ->whereIn('payment_status', self::PAY_UNSETTLED)
            ->whereNotNull('wefact_invoice_id')
            ->orderBy('id')
            ->each(function (OrderInvoice $invoice) use (&$count) {
                try {
                    $this->sync($invoice);
                    $count++;
                } catch (Throwable $e) {
                    Log::warning('WeFact-status ophalen mislukt', ['order_invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
                }
            });

        return $count;
    }

    /** WeFact-factuur (invoice/show) → betaalstatus. Status: 2 verzonden, 3 deels betaald, 4 betaald, 8 credit, 9 vervallen. */
    private function applyStatus(OrderInvoice $invoice, array $data): OrderInvoice
    {
        if (! $data) {
            return $invoice->fresh();
        }

        $outstanding = isset($data['AmountOutstanding']) ? round((float) $data['AmountOutstanding'], 2) : $invoice->amount_outstanding;
        $dueDate     = filled($data['PayBefore'] ?? null) ? substr((string) $data['PayBefore'], 0, 10) : $invoice->due_date?->toDateString();
        $remoteReminders = (int) ($data['Reminders'] ?? 0);

        $status = match ((int) ($data['Status'] ?? 0)) {
            4       => self::PAY_PAID,
            3       => self::PAY_PARTIAL,
            8       => self::PAY_CREDITED,
            9       => self::PAY_CANCELLED,
            default => ($outstanding !== null && $outstanding <= 0) ? self::PAY_PAID : self::PAY_OPEN,
        };
        if (in_array($status, [self::PAY_OPEN, self::PAY_PARTIAL], true) && $dueDate && $dueDate < now()->toDateString()) {
            $status = $status === self::PAY_OPEN ? self::PAY_OVERDUE : self::PAY_PARTIAL;
        }

        $invoice->update([
            'payment_status'     => $status,
            'amount_outstanding' => $status === self::PAY_PAID ? 0 : $outstanding,
            'due_date'           => $dueDate,
            'reminders_count'    => max($invoice->reminders_count, $remoteReminders),
            'last_reminder_at'   => filled($data['ReminderDate'] ?? null) && $remoteReminders >= $invoice->reminders_count
                ? substr((string) $data['ReminderDate'], 0, 10)
                : $invoice->last_reminder_at,
            'synced_at'          => now(),
        ]);

        return $invoice->fresh();
    }

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

    /**
     * Klant zoeken (btw-nummer, anders e-mail) of aanmaken; geeft de DebtorCode terug.
     *
     * Peppol: Belgische bedrijven staan op Peppol onder 0208:{ondernemingsnummer} en/of
     * 9925:be{nummer}. Weigert WeFact elke schrijfwijze, dan gaat de factuur per e-mail
     * (als er een e-mailadres is).
     */
    private function debtor(WeFactClient $api, OrderInvoice $invoice): string
    {
        $candidates = $invoice->channel === OrderInvoice::CHANNEL_PEPPOL ? self::peppolCandidates($invoice->vat_number) : [];

        [$searchAt, $searchFor] = $invoice->vat_number
            ? ['TaxNumber', $invoice->vat_number]
            : ['EmailAddress', $invoice->email];

        $found = $api->call('debtor', 'list', ['searchat' => $searchAt, 'searchfor' => $searchFor, 'limit' => 5])['debtors'] ?? [];
        $match = collect($found)->first(fn ($d) => filled($d['DebtorCode'] ?? null));

        if ($match) {
            $base = array_filter([
                'Identifier'   => $match['Identifier'],
                'EmailAddress' => $invoice->email,
            ], fn ($v) => $v !== null && $v !== '');

            // Staat de klant in WeFact al op Peppol (bv. manueel ingesteld), dan dat adres laten staan.
            if ($candidates) {
                $existing = $api->call('debtor', 'show', ['Identifier' => $match['Identifier']])['debtor'] ?? [];
                if ((int) ($existing['InvoiceMethod'] ?? 0) === 5 && filled($existing['PeppolAddress'] ?? null)) {
                    if (count($base) > 1) {
                        $api->call('debtor', 'edit', $base);
                    }

                    return (string) $match['DebtorCode'];
                }
            }

            $this->withPeppolFallback($invoice, $candidates, fn (?string $peppol) => $api->call('debtor', 'edit', [
                ...$base,
                'InvoiceMethod' => $peppol ? 5 : 0,
                ...($peppol ? ['PeppolAddress' => $peppol] : []),
            ]));

            return (string) $match['DebtorCode'];
        }

        [$initials, $surname] = self::splitName($invoice->contact_name);

        $base = array_filter([
            'CompanyName'  => $invoice->company_name,
            'Initials'     => $initials,
            'SurName'      => $surname ?: ($invoice->company_name ? null : $invoice->contact_name),
            'TaxNumber'    => $invoice->vat_number,
            'Address'      => $invoice->address,
            'ZipCode'      => $invoice->postal_code,
            'City'         => $invoice->city,
            'Country'      => $invoice->country ?: 'BE',
            'EmailAddress' => $invoice->email,
        ], fn ($v) => $v !== null && $v !== '');

        $created = $this->withPeppolFallback($invoice, $candidates, fn (?string $peppol) => $api->call('debtor', 'add', [
            ...$base,
            'InvoiceMethod' => $peppol ? 5 : 0,
            ...($peppol ? ['PeppolAddress' => $peppol] : []),
        ]))['debtor'] ?? [];

        $code = (string) ($created['DebtorCode'] ?? '');
        if ($code === '') {
            throw new RuntimeException('WeFact gaf geen klantnummer terug.');
        }

        return $code;
    }

    /**
     * Klant opslaan met elk Peppol-adres na elkaar; weigert WeFact ze allemaal ("ongeldig Peppol adres"),
     * dan zonder Peppol en de factuur per e-mail. Geen e-mailadres: duidelijke fout voor de frontdesk.
     *
     * WeFact documenteert het formaat van PeppolAddress niet; we proberen de gangbare vormen en
     * onthouden per zaak welke vorm WeFact aanvaardt, zodat die de volgende keer eerst komt.
     */
    private function withPeppolFallback(OrderInvoice $invoice, array $candidates, callable $save): array
    {
        $cacheKey = 'wefact:peppol-format:'.$invoice->tenant_id;
        $preferred = Cache::get($cacheKey);
        if ($preferred && isset($candidates[$preferred])) {
            $candidates = [$preferred => $candidates[$preferred]] + $candidates;
        }

        $lastError = null;
        foreach ($candidates as $format => $peppol) {
            try {
                $result = $save($peppol);
                if ($format !== $preferred) {
                    Cache::forever($cacheKey, $format);
                    Log::info('WeFact Peppol-adres aanvaard', ['format' => $format, 'peppol' => $peppol]);
                }

                return $result;
            } catch (RuntimeException $e) {
                if (stripos($e->getMessage(), 'peppol') === false) {
                    throw $e;
                }
                $lastError = $e;
            }
        }

        if ($candidates) {
            if (! $invoice->email) {
                throw new RuntimeException('WeFact weigerde het Peppol-adres van deze klant ('.reset($candidates).'). Vul een e-mailadres in, dan gaat de factuur per e-mail.');
            }
            Log::warning('WeFact weigerde elk Peppol-adres: factuur per e-mail', [
                'order_invoice_id' => $invoice->id,
                'tried'            => array_values($candidates),
                'error'            => $lastError?->getMessage(),
            ]);
            $invoice->update(['channel' => OrderInvoice::CHANNEL_EMAIL]);
        }

        return $save(null);
    }

    /** Mogelijke schrijfwijzen van het Peppol-adres voor een Belgisch btw-nummer (vorm => adres). */
    public static function peppolCandidates(?string $vat): array
    {
        if (! $vat || ! preg_match('/^BE([01]\d{9})$/', $vat, $m)) {
            return [];
        }

        return [
            'iso0208'   => '0208:'.$m[1],               // 0208:0850037526
            'kbo'       => $m[1],                        // 0850037526
            'iso9925'   => '9925:'.strtolower($vat),     // 9925:be0850037526
            'iso9925uc' => '9925:'.$vat,                 // 9925:BE0850037526
            'vat'       => $vat,                         // BE0850037526
        ];
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
            // Betaald aan de kassa: termijn 0; "Op factuur": betaaltermijn van de zaak
            'Term'            => self::isUnpaidMethod($order->payment_method) ? self::paymentTerm($tenant) : 0,
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
            'invoice', 'factuur', 'transfer', 'overschrijving', 'wire' => 'wire', // achteraf via overschrijving
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
