<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Verwerkt inkomende webhook events van Resend.
 * Resend stuurt POST requests naar /api/webhooks/resend bij elke statuswijziging.
 *
 * Documentatie: https://resend.com/docs/dashboard/webhooks/event-types
 */
class ResendWebhookController extends Controller
{
    // Map Resend event types naar onze statussen + timestamp-velden
    private const EVENT_MAP = [
        'email.sent'            => ['status' => MailLog::STATUS_SENT,       'field' => 'sent_at'],
        'email.delivered'       => ['status' => MailLog::STATUS_DELIVERED,  'field' => 'delivered_at'],
        'email.opened'          => ['status' => MailLog::STATUS_OPENED,     'field' => 'opened_at'],
        'email.clicked'         => ['status' => MailLog::STATUS_CLICKED,    'field' => 'clicked_at'],
        'email.bounced'         => ['status' => MailLog::STATUS_BOUNCED,    'field' => 'bounced_at'],
        'email.complained'      => ['status' => MailLog::STATUS_COMPLAINED, 'field' => 'complained_at'],
        'email.delivery_delayed' => ['status' => MailLog::STATUS_SENT,      'field' => null],
    ];

    public function __invoke(Request $request): Response
    {
        $payload = $request->input();
        $type    = $payload['type'] ?? null;
        $data    = $payload['data'] ?? [];
        $emailId = $data['email_id'] ?? null;

        if (! $type || ! $emailId) {
            return response('Missing type or email_id', 400);
        }

        $mapping = self::EVENT_MAP[$type] ?? null;
        if (! $mapping) {
            // Onbekend event — negeren maar 200 teruggeven zodat Resend niet blijft retrien
            return response('OK', 200);
        }

        $mailLog = MailLog::query()->where('resend_id', $emailId)->first();

        if (! $mailLog) {
            // Mail niet in onze logs — kan gebeuren voor mails verstuurd vóór de module bestond
            Log::debug('ResendWebhook: geen MailLog gevonden voor resend_id=' . $emailId);
            return response('OK', 200);
        }

        $update = ['status' => $mapping['status']];

        if ($mapping['field']) {
            $update[$mapping['field']] = now();
        }

        // Bounce details
        if ($type === 'email.bounced') {
            $update['bounce_type']        = $data['bounce']['type'] ?? null;         // hard | soft
            $update['bounce_description'] = $data['bounce']['message'] ?? null;
        }

        // Alleen upgraden — van opened naar bounced mag niet (status achteruit)
        $statusOrder = [
            MailLog::STATUS_QUEUED    => 0,
            MailLog::STATUS_SENT      => 1,
            MailLog::STATUS_DELIVERED => 2,
            MailLog::STATUS_OPENED    => 3,
            MailLog::STATUS_CLICKED   => 4,
            MailLog::STATUS_BOUNCED   => 5,
            MailLog::STATUS_COMPLAINED => 5,
            MailLog::STATUS_FAILED    => 5,
        ];

        $currentOrder = $statusOrder[$mailLog->status] ?? 0;
        $newOrder     = $statusOrder[$mapping['status']] ?? 0;

        // Bounced en complained zijn altijd definitief, ongeacht huidige status
        $isTerminal = in_array($mapping['status'], [MailLog::STATUS_BOUNCED, MailLog::STATUS_COMPLAINED]);

        if ($newOrder >= $currentOrder || $isTerminal) {
            $mailLog->update($update);
        }

        return response('OK', 200);
    }
}
