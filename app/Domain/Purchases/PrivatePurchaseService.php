<?php

namespace App\Domain\Purchases;

use App\Mail\PrivatePurchaseMail;
use App\Mail\TemplateMail;
use App\Models\AccountingExportSetting;
use App\Models\PrivatePurchase;
use App\Models\Tenant;
use App\Services\MailLogger;
use App\Services\ReservationMailVars;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Aankopen van particulieren: aanmaken, link versturen, ondertekenen en de PDF mailen.
 */
class PrivatePurchaseService
{
    public const MAIL_TYPE_INVITE = 'private-purchase-invite';
    public const MAIL_TYPE_SIGNED = 'private-purchase-signed';

    public function __construct(
        private readonly PurchaseDocumentPdf $pdf,
    ) {
    }

    // ------------------------------------------------------------------
    // Aanmaken / bewerken (backoffice)
    // ------------------------------------------------------------------

    public function create(Tenant $tenant, array $data, ?int $userId): PrivatePurchase
    {
        return DB::transaction(function () use ($tenant, $data, $userId) {
            $year = (int) now(PrivatePurchase::TIMEZONE)->format('Y');

            $last = PrivatePurchase::query()
                ->where('tenant_id', $tenant->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->max('sequence');

            $sequence = ((int) $last) + 1;

            $purchase = new PrivatePurchase([
                'tenant_id' => $tenant->id,
                'year' => $year,
                'sequence' => $sequence,
                'number' => sprintf('AK-%d-%04d', $year, $sequence),
                'status' => PrivatePurchase::STATUS_DRAFT,
                'created_by' => $userId,
            ]);

            $this->fill($purchase, $data);
            $purchase->save();

            return $purchase;
        });
    }

    public function update(PrivatePurchase $purchase, array $data): PrivatePurchase
    {
        if ($purchase->isLocked()) {
            throw new RuntimeException('Dit borderel is al ondertekend of geannuleerd en kan niet meer aangepast worden.');
        }

        $this->fill($purchase, $data);
        $purchase->save();

        return $purchase;
    }

    private function fill(PrivatePurchase $purchase, array $data): void
    {
        $items = $this->normalizeItems($data['items'] ?? []);

        $purchase->fill([
            'items' => $items,
            'total_amount' => $this->total($items),
            'payment_method' => $data['payment_method'] ?? PrivatePurchase::PAYMENT_TRANSFER,
            'purchase_date' => $data['purchase_date'] ?? now(PrivatePurchase::TIMEZONE)->toDateString(),
            'sign_place' => $data['sign_place'] ?? null,
            'invite_email' => $data['invite_email'] ?? null,
            'mail_seller' => (bool) ($data['mail_seller'] ?? true),
            'mail_tenant' => (bool) ($data['mail_tenant'] ?? true),
            'mail_accounting' => (bool) ($data['mail_accounting'] ?? true),
            'extra_recipients' => array_values($data['extra_recipients'] ?? []) ?: null,
            'internal_note' => $data['internal_note'] ?? null,
        ]);
    }

    public function normalizeItems(array $items): array
    {
        return array_values(array_map(fn ($item) => [
            'description' => trim((string) ($item['description'] ?? '')),
            'quantity' => round((float) ($item['quantity'] ?? 1), 2),
            'unit_price' => round((float) ($item['unit_price'] ?? 0), 2),
        ], array_filter($items, fn ($item) => trim((string) ($item['description'] ?? '')) !== '')));
    }

    public function total(array $items): float
    {
        return round(array_sum(array_map(fn ($i) => round($i['quantity'] * $i['unit_price'], 2), $items)), 2);
    }

    // ------------------------------------------------------------------
    // Link versturen
    // ------------------------------------------------------------------

    /**
     * Activeer de link (nieuwe token als er nog geen geldige is) en mail hem optioneel naar de verkoper.
     */
    public function share(PrivatePurchase $purchase, Tenant $tenant, bool $sendMail): PrivatePurchase
    {
        if ($purchase->isLocked()) {
            throw new RuntimeException('Dit borderel is al ondertekend of geannuleerd.');
        }

        if (! $purchase->token || ($purchase->token_expires_at && $purchase->token_expires_at->isPast())) {
            $purchase->token = Str::random(48);
        }

        $purchase->status = PrivatePurchase::STATUS_SENT;
        $purchase->token_expires_at = now()->addDays(PrivatePurchase::LINK_DAYS);
        $purchase->sent_via ??= 'qr';
        $purchase->save();

        if ($sendMail) {
            if (! $purchase->invite_email) {
                throw new RuntimeException('Vul eerst het e-mailadres van de verkoper in.');
            }
            $this->mailInvite($purchase, $tenant);
        }

        return $purchase;
    }

    private function mailInvite(PrivatePurchase $purchase, Tenant $tenant): void
    {
        $subject = 'Aankoopborderel ' . $purchase->number . ' – ' . $tenant->display_name;
        $accent = ReservationMailVars::ACCENT;
        $until = $purchase->token_expires_at?->copy()->setTimezone(PrivatePurchase::TIMEZONE)->format('d/m/Y');

        $body = '<p>Beste,</p>'
            . '<p><strong>' . e($tenant->display_name) . '</strong> koopt goederen van je aan voor een totaal van <strong>'
            . e(PrivatePurchase::money($purchase->total_amount)) . '</strong>:</p>'
            . $this->itemsTable($purchase)
            . '<p>Vul je gegevens in en onderteken het aankoopborderel via onderstaande knop. Daarna ontvang je het document als PDF.</p>'
            . '<p style="margin:24px 0;"><a href="' . e($purchase->publicUrl()) . '" style="display:inline-block;padding:13px 22px;border-radius:10px;background:' . $accent . ';color:#ffffff;font-weight:700;text-decoration:none;">Borderel invullen en ondertekenen</a></p>'
            . '<p style="font-size:13px;color:#64748b;">De link is geldig tot ' . e($until) . '.</p>';

        try {
            Mail::to($purchase->invite_email)->send(new PrivatePurchaseMail(
                mailSubject: $subject,
                bodyHtml: $body,
                tenantName: $tenant->display_name,
                brand: TemplateMail::brandFor($tenant),
                replyToEmail: $tenant->email ?: null,
            ));
        } catch (Throwable $e) {
            Log::error('[PURCHASE] Uitnodiging versturen mislukt', ['purchase_id' => $purchase->id, 'error' => $e->getMessage()]);
            throw new RuntimeException('De link kon niet gemaild worden: ' . $e->getMessage(), 0, $e);
        }

        MailLogger::log(
            toEmail: $purchase->invite_email,
            subject: $subject,
            tenantId: $tenant->id,
            mailType: self::MAIL_TYPE_INVITE,
            htmlBody: $body,
        );

        $purchase->forceFill(['sent_via' => 'email', 'invited_at' => now()])->save();
    }

    // ------------------------------------------------------------------
    // Ondertekenen (publieke link)
    // ------------------------------------------------------------------

    /**
     * @param  array  $seller     gevalideerde gegevens van de verkoper
     * @param  array  $signature  {w, h, strokes}
     */
    public function sign(PrivatePurchase $purchase, array $seller, array $signature, ?string $ip, ?string $userAgent): PrivatePurchase
    {
        $purchase = DB::transaction(function () use ($purchase, $seller, $signature, $ip, $userAgent) {
            /** @var PrivatePurchase $locked */
            $locked = PrivatePurchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if (! $locked->linkIsActive()) {
                throw new RuntimeException('Deze link is niet meer geldig.');
            }

            $iban = $seller['iban'] ?? null;

            $locked->fill([
                'seller_first_name' => $seller['first_name'],
                'seller_last_name' => $seller['last_name'],
                'seller_street' => $seller['street'],
                'seller_postal_code' => $seller['postal_code'],
                'seller_city' => $seller['city'],
                'seller_country' => ($seller['country'] ?? null) ?: 'België',
                'seller_email' => strtolower(trim($seller['email'])),
                'seller_phone' => $seller['phone'] ?? null,
                'seller_iban' => $locked->payment_method === PrivatePurchase::PAYMENT_TRANSFER && $iban ? Iban::normalize($iban) : null,
                'seller_iban_holder' => $locked->payment_method === PrivatePurchase::PAYMENT_TRANSFER ? ($seller['iban_holder'] ?? null) : null,
                'declarations' => collect(PrivatePurchase::DECLARATIONS)
                    ->map(fn ($text, $key) => ['key' => $key, 'text' => $text, 'accepted' => true])
                    ->values()
                    ->all(),
                'signature' => $signature,
                'signed_at' => now(),
                'signed_ip' => $ip,
                'signed_user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
                'status' => PrivatePurchase::STATUS_SIGNED,
            ]);

            $locked->document_hash = hash('sha256', json_encode($this->hashPayload($locked), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $locked->save();

            return $locked;
        });

        $tenant = $purchase->tenant;
        $purchase->loadMissing('creator');

        // PDF maken en bewaren. Een fout hier mag de handtekening niet ongedaan maken.
        try {
            $this->storePdf($purchase, $tenant);
        } catch (Throwable $e) {
            Log::error('[PURCHASE] PDF maken mislukt', ['purchase_id' => $purchase->id, 'error' => $e->getMessage()]);
        }

        $this->mailSigned($purchase, $tenant);

        return $purchase->refresh();
    }

    /** Wat er ondertekend werd, als vaste structuur voor de controlecode. */
    private function hashPayload(PrivatePurchase $p): array
    {
        return [
            'number' => $p->number,
            'tenant_id' => $p->tenant_id,
            'purchase_date' => $p->purchase_date?->toDateString(),
            'items' => $p->items,
            'total' => number_format((float) $p->total_amount, 2, '.', ''),
            'payment_method' => $p->payment_method,
            'seller' => [
                $p->seller_first_name, $p->seller_last_name, $p->seller_street, $p->seller_postal_code,
                $p->seller_city, $p->seller_country, $p->seller_email, $p->seller_phone, $p->seller_iban, $p->seller_iban_holder,
            ],
            'declarations' => $p->declarations,
            'signature' => $p->signature,
            'signed_at' => $p->signed_at?->toIso8601String(),
            'signed_ip' => $p->signed_ip,
        ];
    }

    public function storePdf(PrivatePurchase $purchase, Tenant $tenant): string
    {
        $binary = $this->pdf->build($purchase, $tenant);
        $path = 'private-purchases/' . $tenant->id . '/' . $purchase->number . '.pdf';

        Storage::disk('local')->put($path, $binary);

        $purchase->forceFill([
            'pdf_path' => $path,
            'pdf_hash' => hash('sha256', $binary),
        ])->save();

        return $binary;
    }

    public function pdfBinary(PrivatePurchase $purchase): string
    {
        if ($purchase->pdf_path && Storage::disk('local')->exists($purchase->pdf_path)) {
            return Storage::disk('local')->get($purchase->pdf_path);
        }

        if (! $purchase->isSigned()) {
            throw new RuntimeException('Het borderel is nog niet ondertekend.');
        }

        return $this->storePdf($purchase->loadMissing('creator'), $purchase->tenant);
    }

    public function pdfFilename(PrivatePurchase $purchase): string
    {
        return 'aankoopborderel_' . $purchase->number . '.pdf';
    }

    // ------------------------------------------------------------------
    // Mailen na ondertekening
    // ------------------------------------------------------------------

    /**
     * Interne ontvangers: de zaak zelf, de boekhouding en extra adressen.
     *
     * @return string[]
     */
    public function internalRecipients(PrivatePurchase $purchase, Tenant $tenant): array
    {
        $list = [];

        if ($purchase->mail_tenant && $tenant->email) {
            $list[] = $tenant->email;
        }
        if ($purchase->mail_accounting) {
            $list = array_merge($list, AccountingExportSetting::forTenant($tenant->id)->recipientList());
        }
        $list = array_merge($list, (array) ($purchase->extra_recipients ?? []));

        return array_values(array_unique(array_filter(array_map(fn ($a) => strtolower(trim((string) $a)), $list))));
    }

    /**
     * Verstuur het ondertekende borderel. Gooit niet: fouten komen in mail_error.
     *
     * @return array{sent: string[], errors: string[]}
     */
    public function mailSigned(PrivatePurchase $purchase, Tenant $tenant): array
    {
        $sent = [];
        $errors = [];

        try {
            $pdf = $this->pdfBinary($purchase);
        } catch (Throwable $e) {
            $purchase->forceFill(['mail_error' => 'PDF kon niet gemaakt worden: ' . $e->getMessage()])->save();

            return ['sent' => [], 'errors' => [$e->getMessage()]];
        }

        $filename = $this->pdfFilename($purchase);
        $brand = TemplateMail::brandFor($tenant);
        $total = PrivatePurchase::money($purchase->total_amount);

        // 1. Verkoper
        if ($purchase->mail_seller && $purchase->seller_email) {
            $subject = 'Je aankoopborderel ' . $purchase->number . ' – ' . $tenant->display_name;
            $payment = $purchase->payment_method === PrivatePurchase::PAYMENT_CASH
                ? 'Het bedrag wordt contant betaald.'
                : 'Het bedrag wordt overgeschreven naar ' . e(PrivatePurchase::formatIban($purchase->seller_iban)) . '.';

            $body = '<p>Beste ' . e($purchase->seller_first_name) . ',</p>'
                . '<p>Bedankt. In bijlage vind je het ondertekende aankoopborderel <strong>' . e($purchase->number) . '</strong> voor een totaal van <strong>' . e($total) . '</strong>.</p>'
                . $this->itemsTable($purchase)
                . '<p>' . $payment . '</p>'
                . '<p>Met vriendelijke groeten,<br>' . e($tenant->display_name) . '</p>';

            $this->sendOne([$purchase->seller_email], $subject, $body, $tenant, $brand, $filename, $pdf, $sent, $errors);
        }

        // 2. Zaak + boekhouding + extra (één mail)
        $internal = $this->internalRecipients($purchase, $tenant);
        if ($internal !== []) {
            $signedAt = $purchase->signed_at?->copy()->setTimezone(PrivatePurchase::TIMEZONE)->format('d/m/Y H:i');
            $subject = 'Aankoopborderel ' . $purchase->number . ' ondertekend – ' . $purchase->sellerName() . ' – ' . $total;
            $cell = 'padding:6px 10px;border-bottom:1px solid #e2e8f0;';

            $body = '<p>Het aankoopborderel <strong>' . e($purchase->number) . '</strong> werd ondertekend op ' . e($signedAt) . '.</p>'
                . '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin:12px 0 18px;">'
                . '<tr><td style="' . $cell . 'color:#64748b;">Verkoper</td><td style="' . $cell . '"><strong>' . e($purchase->sellerName()) . '</strong><br>'
                . e($purchase->seller_street) . ', ' . e($purchase->seller_postal_code . ' ' . $purchase->seller_city) . '</td></tr>'
                . '<tr><td style="' . $cell . 'color:#64748b;">Datum aankoop</td><td style="' . $cell . '">' . e($purchase->purchase_date?->format('d/m/Y')) . '</td></tr>'
                . '<tr><td style="' . $cell . 'color:#64748b;">Bedrag</td><td style="' . $cell . '"><strong>' . e($total) . '</strong></td></tr>'
                . '<tr><td style="' . $cell . 'color:#64748b;">Betaling</td><td style="' . $cell . '">' . e($purchase->paymentLabel())
                . ($purchase->seller_iban ? ' · ' . e(PrivatePurchase::formatIban($purchase->seller_iban)) : '') . '</td></tr>'
                . '</table>'
                . $this->itemsTable($purchase)
                . '<p>Het borderel zit als PDF in bijlage.</p>';

            $this->sendOne($internal, $subject, $body, $tenant, $brand, $filename, $pdf, $sent, $errors);
        }

        $purchase->forceFill([
            'mailed_at' => $sent !== [] ? now() : $purchase->mailed_at,
            'mailed_to' => $sent !== [] ? $sent : $purchase->mailed_to,
            'mail_error' => $errors !== [] ? implode(' | ', $errors) : null,
        ])->save();

        return ['sent' => $sent, 'errors' => $errors];
    }

    private function sendOne(array $to, string $subject, string $body, Tenant $tenant, array $brand, string $filename, string $pdf, array &$sent, array &$errors): void
    {
        try {
            Mail::to($to)->send(new PrivatePurchaseMail(
                mailSubject: $subject,
                bodyHtml: $body,
                tenantName: $tenant->display_name,
                brand: $brand,
                pdfName: $filename,
                pdfData: $pdf,
                replyToEmail: $tenant->email ?: null,
            ));

            foreach ($to as $address) {
                MailLogger::log(
                    toEmail: $address,
                    subject: $subject,
                    tenantId: $tenant->id,
                    mailType: self::MAIL_TYPE_SIGNED,
                    htmlBody: $body,
                );
                $sent[] = $address;
            }
        } catch (Throwable $e) {
            Log::error('[PURCHASE] Borderel mailen mislukt', ['to' => $to, 'error' => $e->getMessage()]);
            $errors[] = implode(', ', $to) . ': ' . $e->getMessage();
        }
    }

    private function itemsTable(PrivatePurchase $purchase): string
    {
        $cell = 'padding:6px 10px;border-bottom:1px solid #e2e8f0;';
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin:12px 0 18px;">';

        foreach ($purchase->items ?? [] as $item) {
            $qty = (float) $item['quantity'];
            $html .= '<tr><td style="' . $cell . '">' . e(($qty == floor($qty) ? (int) $qty : $qty) . ' × ' . $item['description']) . '</td>'
                . '<td style="' . $cell . 'text-align:right;white-space:nowrap;">' . e(PrivatePurchase::money($qty * (float) $item['unit_price'])) . '</td></tr>';
        }

        $html .= '<tr><td style="padding:8px 10px;font-weight:700;">Totaal</td><td style="padding:8px 10px;text-align:right;font-weight:700;">'
            . e(PrivatePurchase::money($purchase->total_amount)) . '</td></tr></table>';

        return $html;
    }

    // ------------------------------------------------------------------
    // Foto's
    // ------------------------------------------------------------------

    public function photoDirectory(PrivatePurchase $purchase): string
    {
        return 'private-purchases/' . $purchase->tenant_id . '/photos/' . $purchase->id;
    }

    public function deleteFiles(PrivatePurchase $purchase): void
    {
        foreach ($purchase->photos ?? [] as $photo) {
            if (! empty($photo['path'])) {
                Storage::disk('local')->delete($photo['path']);
            }
        }
        if ($purchase->pdf_path) {
            Storage::disk('local')->delete($purchase->pdf_path);
        }
    }
}
