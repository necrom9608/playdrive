<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\TenantEmailTemplate;
use App\Services\EmailTemplateResolver;
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
                'variables'     => $default['variables'] ?? [],
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

        // Injecteren met voorbeeldwaarden
        $sampleVars = [
            'name'                    => 'Jan Peeters',
            'email'                   => 'jan@voorbeeld.be',
            'phone'                   => '+32 478 12 34 56',
            'event_date'              => date('d/m/Y', strtotime('+14 days')),
            'event_time'              => '14:00',
            'event_type'              => 'Verjaardagsfeest 🎂',
            'stay_option'             => '2 uur',
            'catering_option'         => 'Warme maaltijd',
            'participants_children'   => '8',
            'participants_adults'     => '4',
            'participants_supervisors'=> '2',
            'participants_total'      => '14',
            'tenant_name'             => $currentTenant->tenant->display_name,
            'tenant_email'            => $currentTenant->tenant->email ?? 'info@voorbeeld.be',
            'tenant_phone'            => $currentTenant->tenant->phone ?? '+32 9 123 45 67',
            'access_url'              => url('/reservatie/voorbeeld-token-abc123'),
            'comment_block'           => '<p><strong>Opmerking van de klant:</strong><br>Graag een taart voorzien voor het kind.</p>',
            'outside_hours_block'     => '',
        ];

        $rendered = EmailTemplateResolver::render(
            ['subject' => $subject, 'body' => $body],
            $sampleVars
        );

        $html = $this->wrapInLayout($rendered['subject'], $rendered['body'], $currentTenant->tenant->display_name);

        return response()->json(['html' => $html]);
    }

    // -------------------------------------------------------------------------

    private function labelFor(string $key): string
    {
        return match ($key) {
            'reservation-confirmation-customer' => 'Bevestigingsmail klant',
            'reservation-notification-tenant'   => 'Notificatiemail (intern)',
            default                              => $key,
        };
    }

    private function descriptionFor(string $key): string
    {
        return match ($key) {
            'reservation-confirmation-customer' => 'Verstuurd naar de klant nadat hij het reservatieformulier heeft ingevuld. Bevat de reservatiedetails en een persoonlijke link.',
            'reservation-notification-tenant'   => 'Verstuurd naar jullie eigen e-mailadres bij elke nieuwe reservatie, zodat je direct op de hoogte bent.',
            default                              => '',
        };
    }

    private function wrapInLayout(string $subject, string $body, string $tenantName): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$subject}</title>
<style>
  body { margin: 0; padding: 0; background: #f1f5f9; font-family: ui-sans-serif, system-ui, Arial, sans-serif; color: #0f172a; }
  .wrapper { padding: 40px 16px; }
  .card { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
  .header { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); padding: 28px 40px; }
  .header-title { color: #ffffff; font-size: 18px; font-weight: 700; margin: 0; }
  .body { padding: 32px 40px; font-size: 15px; line-height: 1.7; color: #334155; }
  .body p { margin: 0 0 14px; }
  .body a { color: #3b82f6; }
  .footer { padding: 18px 40px 28px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header"><p class="header-title">{$tenantName}</p></div>
    <div class="body">{$body}</div>
    <div class="footer">© {$tenantName} &middot; Voorbeeld e-mail</div>
  </div>
</div>
</body>
</html>
HTML;
    }
}
