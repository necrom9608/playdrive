<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Accounting\AccountingExportService;
use App\Domain\Accounting\AccountingPeriod;
use App\Http\Controllers\Controller;
use App\Models\AccountingExport;
use App\Models\AccountingExportSetting;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Backoffice: instellingen + logboek + manueel versturen van de dagtotalen naar de boekhouding.
 */
class AccountingExportController extends Controller
{
    public function __construct(
        private readonly AccountingExportService $service,
    ) {
    }

    public function show(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');

        return response()->json(['data' => $this->payload($currentTenant->id())]);
    }

    public function updateSettings(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');

        $request->merge([
            'recipients' => $this->parseEmails($request->input('recipients')),
            'cc' => $this->parseEmails($request->input('cc')),
        ]);

        $data = Validator::make($request->all(), [
            'enabled' => ['required', 'boolean'],
            'recipients' => ['array', 'max:10'],
            'recipients.*' => ['email', 'max:255'],
            'cc' => ['array', 'max:10'],
            'cc.*' => ['email', 'max:255'],
            'frequency' => ['required', 'in:month,quarter'],
            'send_day' => ['required', 'integer', 'between:1,28'],
            'include_invoices' => ['required', 'boolean'],
            'include_payment_methods' => ['required', 'boolean'],
            'visible_groups' => ['nullable', 'array'],
            'visible_groups.*' => ['boolean'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [
            'recipients.*.email' => '":input" is geen geldig e-mailadres.',
            'cc.*.email' => '":input" is geen geldig e-mailadres.',
        ])->after(function ($validator) use ($request) {
            if ($request->boolean('enabled') && empty($request->input('recipients'))) {
                $validator->errors()->add('recipients', 'Vul minstens één e-mailadres van de boekhouding in om automatisch te versturen.');
            }
        })->validate();

        $settings = AccountingExportSetting::forTenant($currentTenant->id());
        $wasEnabled = (bool) $settings->enabled;

        $settings->fill([
            'enabled' => (bool) $data['enabled'],
            'recipients' => $data['recipients'] ?? [],
            'cc' => $data['cc'] ?? [],
            'frequency' => $data['frequency'],
            'send_day' => (int) $data['send_day'],
            'include_invoices' => (bool) $data['include_invoices'],
            'include_payment_methods' => (bool) $data['include_payment_methods'],
            'visible_groups' => array_intersect_key(
                array_merge(AccountingExportSetting::DEFAULT_VISIBLE_GROUPS, $data['visible_groups'] ?? []),
                AccountingExportSetting::DEFAULT_VISIBLE_GROUPS
            ),
            'message' => $data['message'] ?? null,
        ]);

        // Automatisch versturen telt pas vanaf het moment dat het aangezet wordt.
        if ($settings->enabled && ! $wasEnabled) {
            $settings->enabled_at = now();
        }

        $settings->save();

        return response()->json([
            'message' => 'Instellingen opgeslagen.',
            'data' => $this->payload($currentTenant->id()),
        ]);
    }

    public function send(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');

        $validated = $request->validate([
            'mode' => ['required', 'in:month,quarter,range'],
            'year' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
        ]);

        try {
            $period = AccountingPeriod::fromInput($validated);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $user = $request->attributes->get('backoffice_user');

        try {
            $export = $this->service->send(
                $currentTenant->tenant,
                $period,
                AccountingExport::TRIGGER_MANUAL,
                $user?->id,
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'data' => $this->payload($currentTenant->id()),
            ], 422);
        }

        return response()->json([
            'message' => $period->label() . ' verstuurd naar ' . implode(', ', $export->recipients) . '.',
            'data' => $this->payload($currentTenant->id()),
        ]);
    }

    protected function payload(int $tenantId): array
    {
        $settings = AccountingExportSetting::forTenant($tenantId);

        $exports = AccountingExport::query()
            ->with('sender:id,name')
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (AccountingExport $export) => [
                'id' => $export->id,
                'period_type' => $export->period_type,
                'period_label' => $export->period_label,
                'period_start' => $export->period_start?->toDateString(),
                'period_end' => $export->period_end?->toDateString(),
                'trigger' => $export->trigger,
                'status' => $export->status,
                'error' => $export->error,
                'recipients' => $export->recipients ?? [],
                'cc' => $export->cc ?? [],
                'total_incl_vat' => (float) $export->total_incl_vat,
                'order_count' => $export->order_count,
                'sent_by' => $export->sender?->name,
                'sent_at' => $export->sent_at?->toIso8601String(),
                'created_at' => $export->created_at?->toIso8601String(),
            ])
            ->values();

        return [
            'settings' => [
                'enabled' => (bool) $settings->enabled,
                'enabled_at' => $settings->enabled_at?->toIso8601String(),
                'recipients' => $settings->recipientList(),
                'cc' => $settings->ccList(),
                'frequency' => $settings->frequency,
                'send_day' => (int) $settings->send_day,
                'include_invoices' => (bool) $settings->include_invoices,
                'include_payment_methods' => (bool) $settings->include_payment_methods,
                'visible_groups' => $settings->visibleGroups(),
                'message' => $settings->message,
            ],
            'next' => $this->service->nextScheduled($settings, CarbonImmutable::now()->startOfDay()),
            'exports' => $exports,
        ];
    }

    /**
     * Accepteert een array of een tekst met adressen gescheiden door komma, puntkomma of nieuwe lijn.
     */
    protected function parseEmails(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,;]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($email) => strtolower(trim((string) $email)),
            $value
        ))));
    }
}
