<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Mail\TemplateMail;
use App\Models\TenantEmailTemplate;
use App\Services\EmailTemplateResolver;
use App\Services\ReservationMailVars;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404);

        $tenant = $currentTenant->tenant;

        // Tenant-overrides ophalen
        $tenantOverrides = TenantEmailTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('key');

        $defaults      = EmailTemplateResolver::platformDefaults();
        $tenantKeys    = EmailTemplateResolver::tenantKeys();

        $templates = collect($tenantKeys)->map(function (string $key) use ($defaults, $tenantOverrides, $tenant) {
            $default        = $defaults[$key] ?? [];
            $tenantOverride = $tenantOverrides->get($key);
            $adminLayer     = EmailTemplateResolver::getAdminLayer($key);

            // Wat de tenant te zien krijgt als zijn huidige waarde:
            // eigen override > admin-laag > platformdefault
            $effective = $tenantOverride
                ? ['subject' => $tenantOverride->subject, 'body' => $tenantOverride->body]
                : $adminLayer;

            return [
                'key'           => $key,
                'label'         => $this->labelFor($key),
                'description'   => $this->descriptionFor($key),
                'variables'     => $default['variables'] ?? EmailTemplateResolver::VARIABLES,
                'subject'       => $effective['subject'],
                'body'          => $effective['body'],
                'is_customized' => $tenantOverride !== null,
                // Wat de tenant als "standaard" ziet (de admin-laag)
                'default_subject' => $adminLayer['subject'],
                'default_body'    => $adminLayer['body'],
            ];
        })->values()->all();

        return response()->json(['templates' => $templates]);
    }

    public function update(Request $request, string $key, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404);
        abort_unless(in_array($key, EmailTemplateResolver::tenantKeys()), 404, 'Template niet gevonden.');

        $tenant = $currentTenant->tenant;

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:500'],
            'body'    => ['required', 'string'],
        ]);

        TenantEmailTemplate::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => $key],
            ['subject' => $data['subject'], 'body' => $data['body']]
        );

        return response()->json(['ok' => true]);
    }

    public function reset(string $key, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404);
        abort_unless(in_array($key, EmailTemplateResolver::tenantKeys()), 404, 'Template niet gevonden.');

        TenantEmailTemplate::query()
            ->where('tenant_id', $currentTenant->tenant->id)
            ->where('key', $key)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function preview(Request $request, string $key, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404);
        abort_unless(in_array($key, EmailTemplateResolver::tenantKeys()), 404, 'Template niet gevonden.');

        $data = $request->validate([
            'subject' => ['nullable', 'string'],
            'body'    => ['nullable', 'string'],
        ]);

        // Gebruik de meegestuurde waarden als startpunt, anders de effectieve laag
        $effective = EmailTemplateResolver::resolve($key, $currentTenant->tenant);
        $subject   = $data['subject'] ?? $effective['subject'];
        $body      = $data['body']    ?? $effective['body'];

        // Voorbeeldwaarden en exact dezelfde layout als de echte mail
        $tenant   = $currentTenant->tenant;
        $rendered = EmailTemplateResolver::render(
            ['subject' => $subject, 'body' => $body],
            ReservationMailVars::sample($tenant)
        );

        $html = view('emails.template-mail', [
            'mailSubject' => $rendered['subject'],
            'bodyHtml'    => $rendered['body'],
            'tenantName'  => $tenant->display_name,
            'brand'       => TemplateMail::brandFor($tenant),
        ])->render();

        return response()->json(['html' => $html]);
    }

    // -------------------------------------------------------------------------

    private function labelFor(string $key): string
    {
        return match ($key) {
            'reservation-confirmation-customer' => 'Bevestigingsmail klant',
            'reservation-notification-tenant'   => 'Melding nieuwe reservatie (intern)',
            'reservation-pending-customer'      => 'Aanvraag buiten de openingsuren (klant)',
            'reservation-updated-customer'      => 'Reservatie aangepast (klant)',
            'reservation-cancelled-customer'    => 'Reservatie geannuleerd (klant)',
            default                              => $key,
        };
    }

    private function descriptionFor(string $key): string
    {
        return match ($key) {
            'reservation-confirmation-customer' => 'Naar de klant na het reservatieformulier: datum, details, knoppen om de reservatie te bekijken of in de agenda te zetten, en jullie adres. Met een agenda-bijlage (.ics).',
            'reservation-notification-tenant'   => 'Naar jullie eigen e-mailadres bij elke nieuwe reservatie of aanvraag, met de contactgegevens van de klant, opmerkingen en factuurgegevens.',
            'reservation-pending-customer'      => 'Naar de klant bij een aanvraag buiten de openingsuren: jullie bekijken ze eerst. Na bevestiging volgt de gewone bevestigingsmail.',
            'reservation-updated-customer'      => 'Naar de klant wanneer hij zelf zijn reservatie aanpast.',
            'reservation-cancelled-customer'    => 'Naar de klant wanneer hij zijn reservatie annuleert.',
            default                              => '',
        };
    }
}
