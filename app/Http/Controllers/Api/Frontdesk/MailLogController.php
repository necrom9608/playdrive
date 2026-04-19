<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailLogController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $search  = trim((string) $request->input('search', ''));
        $statuses = collect($request->input('statuses', []))->filter()->values()->all();
        $types   = collect($request->input('types', []))->filter()->values()->all();

        $query = MailLog::query()
            ->where('tenant_id', $currentTenant->id())
            ->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('to_email', 'like', '%' . $search . '%')
                ->orWhere('to_name', 'like', '%' . $search . '%')
                ->orWhere('subject', 'like', '%' . $search . '%')
            );
        }

        if (! empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        if (! empty($types)) {
            $query->whereIn('mail_type', $types);
        }

        $logs = $query->limit(200)->get();

        $base = fn () => MailLog::query()->where('tenant_id', $currentTenant->id());

        $summary = [
            'total'     => ($base)()->count(),
            'delivered' => ($base)()->whereIn('status', [
                MailLog::STATUS_DELIVERED,
                MailLog::STATUS_OPENED,
                MailLog::STATUS_CLICKED,
            ])->count(),
            'opened'    => ($base)()->whereIn('status', [
                MailLog::STATUS_OPENED,
                MailLog::STATUS_CLICKED,
            ])->count(),
            'issues'    => ($base)()->whereIn('status', [
                MailLog::STATUS_BOUNCED,
                MailLog::STATUS_COMPLAINED,
                MailLog::STATUS_FAILED,
            ])->count(),
        ];

        return response()->json([
            'data' => [
                'summary' => $summary,
                'logs'    => $logs->map(fn (MailLog $log) => $this->map($log))->values(),
            ],
        ]);
    }

    public function show(CurrentTenant $currentTenant, MailLog $mailLog): JsonResponse
    {
        abort_unless((int) $mailLog->tenant_id === (int) $currentTenant->id(), 404);

        return response()->json(['data' => $this->map($mailLog, withBody: true)]);
    }

    private function map(MailLog $log, bool $withBody = false): array
    {
        return [
            'id'                 => $log->id,
            'to_email'           => $log->to_email,
            'to_name'            => $log->to_name,
            'account_id'         => $log->account_id,
            'subject'            => $log->subject,
            'mail_type'          => $log->mail_type,
            'mail_type_label'    => $this->typeLabel($log->mail_type),
            'resend_id'          => $log->resend_id,
            'status'             => $log->status,
            'status_label'       => MailLog::statusLabel($log->status),
            'has_issue'          => $log->hasIssue(),
            'sent_at'            => $log->sent_at?->toIso8601String(),
            'sent_at_label'      => $log->sent_at?->format('d/m/Y H:i'),
            'delivered_at'       => $log->delivered_at?->toIso8601String(),
            'opened_at'          => $log->opened_at?->toIso8601String(),
            'clicked_at'         => $log->clicked_at?->toIso8601String(),
            'bounced_at'         => $log->bounced_at?->toIso8601String(),
            'complained_at'      => $log->complained_at?->toIso8601String(),
            'bounce_type'        => $log->bounce_type,
            'bounce_description' => $log->bounce_description,
            'created_at'         => $log->created_at?->toIso8601String(),
            'created_at_label'   => $log->created_at?->format('d/m/Y H:i'),
            'html_body'          => $withBody ? $log->html_body : null,
        ];
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'member_invite'       => 'Uitnodiging lid',
            'password_reset'      => 'Wachtwoord reset',
            'member_confirmation' => 'Bevestiging abonnement',
            'member_expiring'     => 'Abonnement vervalt',
            'member_expired'      => 'Abonnement vervallen',
            'receipt'             => 'Kassaticket',
            default               => $type ?? 'Overig',
        };
    }
}
