<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantEmailTemplate;

/**
 * Resolveert e-mailtemplates via een drie-lagen fallback:
 *   1. Tenant-override (in DB)
 *   2. Admin-override (in JSON-bestand)
 *   3. Hardcoded platformdefault
 */
class EmailTemplateResolver
{
    /**
     * Geeft het geresolveerde template terug als ['subject' => ..., 'body' => ...].
     */
    public static function resolve(string $key, Tenant $tenant): array
    {
        // Laag 1: tenant-override
        $tenantOverride = TenantEmailTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', $key)
            ->first();

        if ($tenantOverride) {
            return [
                'subject' => $tenantOverride->subject,
                'body'    => $tenantOverride->body,
            ];
        }

        // Laag 2: admin-override
        $adminOverrides = self::loadAdminOverrides();
        if (isset($adminOverrides[$key])) {
            return [
                'subject' => $adminOverrides[$key]['subject'],
                'body'    => $adminOverrides[$key]['body'],
            ];
        }

        // Laag 3: platformdefault
        $defaults = self::platformDefaults();
        if (isset($defaults[$key])) {
            return [
                'subject' => $defaults[$key]['subject'],
                'body'    => $defaults[$key]['body'],
            ];
        }

        return [
            'subject' => 'Bericht van ' . $tenant->display_name,
            'body'    => '',
        ];
    }

    /**
     * Vervangt {{variabelen}} in onderwerp en body.
     */
    public static function render(array $template, array $variables): array
    {
        $subject = $template['subject'];
        $body    = $template['body'];

        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $subject     = str_replace($placeholder, (string) $value, $subject);
            $body        = str_replace($placeholder, (string) $value, $body);
        }

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Geeft de effectieve template terug die een tenant ziet als startpunt
     * (voor de backoffice editor): admin-override of platformdefault.
     */
    public static function getAdminLayer(string $key): array
    {
        $adminOverrides = self::loadAdminOverrides();

        if (isset($adminOverrides[$key])) {
            return [
                'subject' => $adminOverrides[$key]['subject'],
                'body'    => $adminOverrides[$key]['body'],
            ];
        }

        $defaults = self::platformDefaults();

        return $defaults[$key] ?? ['subject' => '', 'body' => ''];
    }

    /**
     * Alle template-keys die beschikbaar zijn voor tenants.
     */
    public static function tenantKeys(): array
    {
        return [
            'reservation-confirmation-customer',
            'reservation-notification-tenant',
            'reservation-updated-customer',
            'reservation-cancelled-customer',
        ];
    }

    // -------------------------------------------------------------------------
    // Platform defaults
    // -------------------------------------------------------------------------

    public static function platformDefaults(): array
    {
        return [
            'reservation-confirmation-customer' => [
                'subject'   => 'Reservatie bevestigd – {{tenant_name}}',
                'variables' => [
                    'name', 'event_date', 'event_time', 'event_type',
                    'stay_option', 'catering_option', 'participants_total',
                    'tenant_name', 'tenant_phone', 'tenant_email', 'access_url',
                ],
                'body'      => <<<'HTML'
<p>Hallo {{name}},</p>

<p>We hebben je reservatie goed ontvangen! Hieronder vind je een samenvatting.</p>

<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;">
  <tr><td style="padding:6px 0;color:#64748b;width:40%;">Datum</td><td style="padding:6px 0;font-weight:600;">{{event_date}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Startuur</td><td style="padding:6px 0;font-weight:600;">{{event_time}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Type event</td><td style="padding:6px 0;">{{event_type}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Formule</td><td style="padding:6px 0;">{{stay_option}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Catering</td><td style="padding:6px 0;">{{catering_option}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Aantal personen</td><td style="padding:6px 0;">{{participants_total}}</td></tr>
</table>

<p>Heb je vragen? Contacteer ons via <a href="mailto:{{tenant_email}}">{{tenant_email}}</a> of {{tenant_phone}}.</p>

<p style="margin-top:24px;">
  <a href="{{access_url}}" style="display:inline-block;background:#3b82f6;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;font-size:14px;">Bekijk je reservatie</a>
</p>

<p style="font-size:12px;color:#94a3b8;margin-top:8px;">Via bovenstaande knop kan je je reservatie altijd raadplegen. Je hebt geen account nodig.</p>

<p>Met vriendelijke groet,<br>{{tenant_name}}</p>
HTML,
            ],

            'reservation-notification-tenant' => [
                'subject'   => 'Nieuwe reservatie – {{name}} op {{event_date}}',
                'variables' => [
                    'name', 'email', 'phone', 'event_date', 'event_time',
                    'event_type', 'stay_option', 'catering_option',
                    'participants_total', 'participants_children',
                    'participants_adults', 'participants_supervisors',
                    'comment', 'outside_opening_hours', 'tenant_name',
                ],
                'body'      => <<<'HTML'
<p>Er is een nieuwe reservatie binnengekomen.</p>

<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;">
  <tr><td style="padding:6px 0;color:#64748b;width:40%;">Naam</td><td style="padding:6px 0;font-weight:600;">{{name}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">E-mail</td><td style="padding:6px 0;">{{email}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Telefoon</td><td style="padding:6px 0;">{{phone}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Datum</td><td style="padding:6px 0;font-weight:600;">{{event_date}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Startuur</td><td style="padding:6px 0;font-weight:600;">{{event_time}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Type event</td><td style="padding:6px 0;">{{event_type}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Formule</td><td style="padding:6px 0;">{{stay_option}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Catering</td><td style="padding:6px 0;">{{catering_option}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Kinderen</td><td style="padding:6px 0;">{{participants_children}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Volwassenen</td><td style="padding:6px 0;">{{participants_adults}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Begeleiders</td><td style="padding:6px 0;">{{participants_supervisors}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Totaal</td><td style="padding:6px 0;">{{participants_total}}</td></tr>
</table>

{{comment_block}}

{{outside_hours_block}}

<p style="font-size:12px;color:#94a3b8;">Beheer deze reservatie via de PlayDrive backoffice.</p>
HTML,
            ],

            'reservation-updated-customer' => [
                'subject'   => 'Reservatie gewijzigd – {{tenant_name}}',
                'variables' => [
                    'name', 'event_date', 'event_time', 'event_type',
                    'stay_option', 'participants_total', 'tenant_name',
                    'tenant_phone', 'tenant_email', 'access_url',
                ],
                'body'      => <<<'HTML'
<p>Hallo {{name}},</p>

<p>Je reservatie werd succesvol aangepast. Hieronder vind je de bijgewerkte gegevens.</p>

<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;">
  <tr><td style="padding:6px 0;color:#64748b;width:40%;">Datum</td><td style="padding:6px 0;font-weight:600;">{{event_date}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Startuur</td><td style="padding:6px 0;font-weight:600;">{{event_time}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Type event</td><td style="padding:6px 0;">{{event_type}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Formule</td><td style="padding:6px 0;">{{stay_option}}</td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Aantal personen</td><td style="padding:6px 0;">{{participants_total}}</td></tr>
</table>

<p>Heb je vragen? Contacteer ons via <a href="mailto:{{tenant_email}}">{{tenant_email}}</a> of {{tenant_phone}}.</p>

<p style="margin-top:24px;">
  <a href="{{access_url}}" style="display:inline-block;background:#3b82f6;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;font-size:14px;">Bekijk je reservatie</a>
</p>

<p>Met vriendelijke groet,<br>{{tenant_name}}</p>
HTML,
            ],

            'reservation-cancelled-customer' => [
                'subject'   => 'Reservatie geannuleerd – {{tenant_name}}',
                'variables' => [
                    'name', 'event_date', 'event_time', 'event_type',
                    'tenant_name', 'tenant_phone', 'tenant_email',
                ],
                'body'      => <<<'HTML'
<p>Hallo {{name}},</p>

<p>Je reservatie op <strong>{{event_date}} om {{event_time}}</strong> ({{event_type}}) werd geannuleerd.</p>

<p>Heb je vragen of wil je een nieuwe reservatie maken? Neem gerust contact op via <a href="mailto:{{tenant_email}}">{{tenant_email}}</a> of {{tenant_phone}}.</p>

<p>Met vriendelijke groet,<br>{{tenant_name}}</p>
HTML,
            ],
        ];
    }

    // -------------------------------------------------------------------------

    private static function loadAdminOverrides(): array
    {
        $path = storage_path('app/email-template-overrides.json');

        if (! file_exists($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }
}
