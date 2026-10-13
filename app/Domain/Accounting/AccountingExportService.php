<?php

namespace App\Domain\Accounting;

use App\Mail\AccountingExportMail;
use App\Models\AccountingExport;
use App\Models\AccountingExportSetting;
use App\Models\Tenant;
use App\Services\MailLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Verstuurt dagtotalen per mail naar de boekhouding en houdt het logboek bij.
 */
class AccountingExportService
{
    public const MAIL_TYPE = 'accounting-day-totals';

    public function __construct(
        private readonly DayTotalsReportService $reports,
    ) {
    }

    /**
     * Verstuur de dagtotalen van $period. Logt altijd een AccountingExport, ook bij een fout.
     *
     * @throws RuntimeException als er geen ontvangers zijn of het versturen mislukt
     */
    public function send(
        Tenant $tenant,
        AccountingPeriod $period,
        string $trigger,
        ?int $userId = null,
        ?AccountingExportSetting $settings = null,
    ): AccountingExport {
        $settings ??= AccountingExportSetting::forTenant($tenant->id);

        $recipients = $settings->recipientList();
        $cc = $settings->ccList();

        if ($recipients === []) {
            throw new RuntimeException('Er is nog geen e-mailadres van de boekhouding ingesteld.');
        }

        $report = $this->reports->build($tenant->id, $period, (bool) $settings->include_invoices);
        $xlsx = $this->reports->buildXlsx($report, $settings->visibleGroups(), (bool) $settings->include_payment_methods);

        $filename = 'dagtotalen_' . str($tenant->slug ?: $tenant->name)->slug() . '_' . $period->filenameSlug() . '.xlsx';
        $subject = 'Dagtotalen ' . $tenant->display_name . ' – ' . $period->label();
        $body = $this->buildBody($tenant, $period, $report, $settings);

        $export = new AccountingExport([
            'tenant_id' => $tenant->id,
            'period_type' => $period->type,
            'period_start' => $period->start->toDateString(),
            'period_end' => $period->end->toDateString(),
            'period_label' => $period->label(),
            'trigger' => $trigger,
            'recipients' => $recipients,
            'cc' => $cc ?: null,
            'total_incl_vat' => $report['totals']['total_incl_vat'],
            'order_count' => $report['order_count'],
            'filename' => $filename,
            'sent_by' => $userId,
        ]);

        try {
            $mail = new AccountingExportMail(
                mailSubject: $subject,
                bodyHtml: $body,
                tenantName: $tenant->display_name,
                attachmentName: $filename,
                attachmentData: $xlsx,
                replyToEmail: $tenant->email ?: null,
            );

            $pending = Mail::to($recipients);
            if ($cc !== []) {
                $pending->cc($cc);
            }
            $pending->send($mail);

            foreach (array_merge($recipients, $cc) as $address) {
                MailLogger::log(
                    toEmail: $address,
                    subject: $subject,
                    tenantId: $tenant->id,
                    mailType: self::MAIL_TYPE,
                    htmlBody: $body,
                );
            }

            $export->fill(['status' => AccountingExport::STATUS_SENT, 'sent_at' => now()])->save();
        } catch (Throwable $e) {
            Log::error('[ACCOUNTING] Versturen dagtotalen mislukt', [
                'tenant_id' => $tenant->id,
                'period' => $period->label(),
                'error' => $e->getMessage(),
            ]);

            $export->fill([
                'status' => AccountingExport::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            throw new RuntimeException('Versturen mislukt: ' . $e->getMessage(), 0, $e);
        }

        return $export;
    }

    /**
     * Welke periode moet er op $today automatisch verstuurd worden? Null = niets te doen.
     *
     * Regels:
     * - enkel de laatst afgelopen periode (vorige maand / vorig kwartaal);
     * - vanaf de ingestelde verzenddag (bv. de 5e), daarna haalt elke volgende run hem in;
     * - niet als de verzenddag vóór het aanzetten van de automatische verzending lag;
     * - niet als die periode al verstuurd werd ná het einde van de periode (auto of manueel).
     */
    public function duePeriod(AccountingExportSetting $settings, CarbonImmutable $today): ?AccountingPeriod
    {
        if (! $settings->enabled || $settings->recipientList() === []) {
            return null;
        }

        $period = AccountingPeriod::lastCompleted($settings->frequency, $today);
        $sendDate = $period->sendDate((int) $settings->send_day);

        if ($today->lessThan($sendDate)) {
            return null;
        }

        if ($settings->enabled_at && $sendDate->lessThan(CarbonImmutable::parse($settings->enabled_at)->startOfDay())) {
            return null;
        }

        return $this->alreadySent($settings->tenant_id, $period) ? null : $period;
    }

    /**
     * Eerstvolgende automatische verzending (voor weergave in de backoffice).
     */
    public function nextScheduled(AccountingExportSetting $settings, CarbonImmutable $today): ?array
    {
        if (! $settings->enabled) {
            return null;
        }

        $due = $this->duePeriod($settings, $today);
        if ($due) {
            // Verzenddag is al voorbij: gaat mee met de volgende dagelijkse run.
            return ['period' => $due->toArray(), 'send_date' => null, 'overdue' => true];
        }

        $period = AccountingPeriod::lastCompleted($settings->frequency, $today);

        // Zolang de verzenddag van de laatst afgelopen periode nog moet komen, is dat de volgende.
        $sendDate = $period->sendDate((int) $settings->send_day);
        if ($today->lessThan($sendDate) && ! $this->alreadySent($settings->tenant_id, $period)
            && (! $settings->enabled_at || ! $sendDate->lessThan(CarbonImmutable::parse($settings->enabled_at)->startOfDay()))) {
            return ['period' => $period->toArray(), 'send_date' => $sendDate->toDateString(), 'overdue' => false];
        }

        $next = $period->next();

        return ['period' => $next->toArray(), 'send_date' => $next->sendDate((int) $settings->send_day)->toDateString(), 'overdue' => false];
    }

    public function alreadySent(int $tenantId, AccountingPeriod $period): bool
    {
        return AccountingExport::query()
            ->where('tenant_id', $tenantId)
            ->where('status', AccountingExport::STATUS_SENT)
            ->whereDate('period_start', $period->start->toDateString())
            ->whereDate('period_end', $period->end->toDateString())
            ->where('sent_at', '>', $period->end)
            ->exists();
    }

    protected function buildBody(Tenant $tenant, AccountingPeriod $period, array $report, AccountingExportSetting $settings): string
    {
        $money = fn ($value) => '€ ' . number_format((float) $value, 2, ',', '.');
        $cell = 'padding:6px 10px;border-bottom:1px solid #e2e8f0;';
        $right = $cell . 'text-align:right;';

        $html = '<p>Beste,</p>';
        $html .= '<p>In bijlage de dagtotalen van <strong>' . e($tenant->display_name) . '</strong> voor <strong>'
            . e($period->label()) . '</strong> (' . $period->start->format('d/m/Y') . ' – ' . $period->end->format('d/m/Y') . ').</p>';

        if (filled($settings->message)) {
            $html .= '<p>' . nl2br(e($settings->message)) . '</p>';
        }

        // Btw-samenvatting
        $html .= '<p style="margin-top:20px;"><strong>Omzet per btw-tarief</strong></p>';
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
        $html .= '<tr><th style="' . $cell . 'text-align:left;">Tarief</th><th style="' . $right . '">Excl.</th><th style="' . $right . '">Btw</th><th style="' . $right . '">Incl.</th></tr>';

        foreach ($report['totals']['vat_breakdown'] as $rate => $values) {
            if ((float) $values['incl'] == 0.0 && (float) $values['excl'] == 0.0) {
                continue;
            }

            $html .= '<tr><td style="' . $cell . '">' . e($rate) . '%</td><td style="' . $right . '">' . $money($values['excl'])
                . '</td><td style="' . $right . '">' . $money($values['vat']) . '</td><td style="' . $right . '">' . $money($values['incl']) . '</td></tr>';
        }

        $html .= '<tr><td style="' . $cell . 'font-weight:700;">Totaal</td><td style="' . $right . 'font-weight:700;">' . $money($report['totals']['total_excl_vat'])
            . '</td><td style="' . $right . 'font-weight:700;">' . $money($report['totals']['total_vat'])
            . '</td><td style="' . $right . 'font-weight:700;">' . $money($report['totals']['total_incl_vat']) . '</td></tr>';
        $html .= '</table>';

        // Betaalwijzen
        if ($settings->include_payment_methods && $report['payments']['methods'] !== []) {
            $html .= '<p style="margin-top:20px;"><strong>Ontvangen per betaalwijze</strong></p>';
            $html .= '<table style="width:100%;border-collapse:collapse;font-size:14px;">';

            foreach ($report['payments']['methods'] as $method) {
                $html .= '<tr><td style="' . $cell . '">' . e($method['label']) . '</td><td style="' . $right . '">'
                    . $money($report['payments']['totals']['methods'][$method['key']] ?? 0) . '</td></tr>';
            }

            if ((float) $report['payments']['totals']['discount'] > 0) {
                $html .= '<tr><td style="' . $cell . 'color:#64748b;">Waarvan manuele kortingen (niet ontvangen)</td><td style="' . $right . 'color:#64748b;">'
                    . $money($report['payments']['totals']['discount']) . '</td></tr>';
            }

            $html .= '<tr><td style="' . $cell . 'font-weight:700;">Totaal ontvangen</td><td style="' . $right . 'font-weight:700;">'
                . $money($report['payments']['totals']['total']) . '</td></tr>';
            $html .= '</table>';
        }

        $html .= '<p style="margin-top:20px;font-size:13px;color:#64748b;">'
            . ($settings->include_invoices ? 'Factuurverkopen zijn inbegrepen.' : 'Factuurverkopen zijn niet inbegrepen (die worden apart gefactureerd).')
            . ' Het volledige overzicht per dag staat in de Excel-bijlage.</p>';

        $html .= '<p>Met vriendelijke groeten,<br>' . e($tenant->display_name) . '</p>';

        return $html;
    }
}
