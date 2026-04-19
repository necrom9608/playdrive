<?php

namespace App\Services;

use App\Models\MailLog;

class MailLogger
{
    /**
     * Log een uitgaande mail en geef het MailLog record terug.
     * Roep dit aan NA het versturen, zodat je de resend_id kan meegeven.
     */
    public static function log(
        string  $toEmail,
        string  $subject,
        ?string $toName       = null,
        ?int    $tenantId     = null,
        ?int    $accountId    = null,
        ?string $mailType     = null,
        ?string $htmlBody     = null,
        ?string $resendId     = null,
        string  $status       = MailLog::STATUS_SENT,
    ): MailLog {
        return MailLog::query()->create([
            'tenant_id'   => $tenantId,
            'to_email'    => strtolower(trim($toEmail)),
            'to_name'     => $toName,
            'account_id'  => $accountId,
            'subject'     => $subject,
            'mail_type'   => $mailType,
            'html_body'   => $htmlBody,
            'resend_id'   => $resendId,
            'status'      => $status,
            'sent_at'     => now(),
        ]);
    }
}
