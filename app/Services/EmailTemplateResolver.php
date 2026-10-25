<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantEmailTemplate;

/**
 * Resolveert e-mailtemplates via een drie-lagen fallback:
 *   1. Tenant-override (in DB)
 *   2. Admin-override (in JSON-bestand)
 *   3. Hardcoded platformdefault
 *
 * Taal: voor een klant die in het Frans of Engels reserveerde, telt enkel een
 * tenant-override met sleutel "{key}.fr" / "{key}.en" (de gewone overrides
 * zijn Nederlands); anders de platformdefault in die taal.
 */
class EmailTemplateResolver
{
    /**
     * Geeft het geresolveerde template terug als ['subject' => ..., 'body' => ...].
     */
    public static function resolve(string $key, Tenant $tenant, string $locale = 'nl'): array
    {
        if ($locale !== 'nl') {
            $eigen = TenantEmailTemplate::query()
                ->where('tenant_id', $tenant->id)
                ->where('key', $key . '.' . $locale)
                ->first();
            if ($eigen) {
                return ['subject' => $eigen->subject, 'body' => $eigen->body];
            }
            $vertaald = self::platformDefaults($locale)[$key] ?? null;
            if ($vertaald) {
                return ['subject' => $vertaald['subject'], 'body' => $vertaald['body']];
            }
        }

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
            'reservation-pending-customer',
            'reservation-updated-customer',
            'reservation-cancelled-customer',
        ];
    }

    /** Placeholders die in elke reservatiemail beschikbaar zijn (voor de editor). */
    public const VARIABLES = [
        'first_name', 'name', 'email', 'phone',
        'event_date', 'event_date_long', 'event_time', 'event_time_end', 'event_period',
        'event_type', 'stay_option', 'catering_option',
        'participants_summary', 'participants_total', 'participants_children', 'participants_adults', 'participants_supervisors',
        'comment', 'access_url', 'calendar_url', 'maps_url',
        'tenant_name', 'tenant_email', 'tenant_phone', 'tenant_address', 'tenant_website',
        'when_block', 'details_block', 'buttons_block', 'request_buttons_block', 'venue_block',
        'customer_block', 'invoice_block', 'comment_block', 'outside_hours_block', 'cancellation_note',
    ];

    // -------------------------------------------------------------------------
    // Platform defaults
    // -------------------------------------------------------------------------
    //
    // V2 (okt 2026): rijkere mails met kant-en-klare blokken (zie
    // ReservationMailVars): datumvak, details, knoppen (reservatie, agenda,
    // route), adres en contact van de zaak.

    public static function platformDefaults(string $locale = 'nl'): array
    {
        if ($locale === 'fr' || $locale === 'en') {
            // Melding naar de zaak blijft Nederlands
            return array_merge(self::platformDefaults('nl'), $locale === 'fr' ? self::defaultsFr() : self::defaultsEn());
        }

        $groet = '<p style="margin:26px 0 0;">Tot dan!<br><strong>{{tenant_name}}</strong></p>';

        return [
            'reservation-confirmation-customer' => [
                'subject'   => 'Je reservatie bij {{tenant_name}} – {{event_date_long}}',
                'variables' => self::VARIABLES,
                'body'      => <<<HTML
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Hallo {{first_name}},</p>
<p>Leuk dat jullie komen! We hebben je reservatie goed ontvangen. Hieronder vind je alles op een rij.</p>

{{when_block}}

{{details_block}}

{{comment_block}}

{{buttons_block}}

<p style="font-size:13px;color:#64748b;margin:4px 0 0;">{{cancellation_note}} De afspraak zit ook als bijlage bij deze mail, zodat je ze met één klik in je agenda zet.</p>

{{venue_block}}

<p>Vragen of iets doorgeven? Antwoord gewoon op deze mail of bel ons op {{tenant_phone}}.</p>
{$groet}
HTML,
            ],

            'reservation-notification-tenant' => [
                'subject'   => 'Nieuwe reservatie: {{event_type}} op {{event_date_long}} ({{participants_total}} pers.) – {{name}}',
                'variables' => self::VARIABLES,
                'body'      => <<<'HTML'
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Nieuwe reservatie</p>
<p>Er kwam net een reservatie binnen via de website.</p>

{{outside_hours_block}}

{{when_block}}

<p style="margin:20px 0 0;font-weight:700;color:#0f172a;">Klant</p>
{{customer_block}}

<p style="margin:20px 0 0;font-weight:700;color:#0f172a;">Reservatie</p>
{{details_block}}

{{comment_block}}

{{invoice_block}}

<p style="font-size:12px;color:#94a3b8;margin-top:24px;">Beheer deze reservatie in PlayDrive (Reservaties).</p>
HTML,
            ],

            'reservation-pending-customer' => [
                'subject'   => 'We bekijken je aanvraag – {{tenant_name}}',
                'variables' => self::VARIABLES,
                'body'      => <<<HTML
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Hallo {{first_name}},</p>
<p>Bedankt voor je aanvraag! Je koos een moment buiten onze gewone openingsuren, dus we bekijken eerst of het lukt. Je hoort zo snel mogelijk van ons.</p>

{{when_block}}

{{details_block}}

{{comment_block}}

{{request_buttons_block}}

{{venue_block}}

<p>Vragen? Antwoord gewoon op deze mail of bel ons op {{tenant_phone}}.</p>
<p style="margin:26px 0 0;">Met vriendelijke groet,<br><strong>{{tenant_name}}</strong></p>
HTML,
            ],

            'reservation-updated-customer' => [
                'subject'   => 'Je reservatie is aangepast – {{event_date_long}}',
                'variables' => self::VARIABLES,
                'body'      => <<<HTML
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Hallo {{first_name}},</p>
<p>Je wijziging is goed doorgekomen. Dit zijn de nieuwe gegevens van je reservatie.</p>

{{when_block}}

{{details_block}}

{{comment_block}}

{{buttons_block}}

<p style="font-size:13px;color:#64748b;margin:4px 0 0;">{{cancellation_note}}</p>

{{venue_block}}
{$groet}
HTML,
            ],

            'reservation-cancelled-customer' => [
                'subject'   => 'Je reservatie is geannuleerd – {{tenant_name}}',
                'variables' => self::VARIABLES,
                'body'      => <<<'HTML'
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Hallo {{first_name}},</p>
<p>Je reservatie voor <strong>{{event_type}}</strong> op <strong>{{event_date_long}}</strong> om <strong>{{event_time}}</strong> is geannuleerd.</p>
<p>Jammer dat het niet lukt. Wil je een nieuwe datum kiezen, of was dit een vergissing? Laat het ons gerust weten, we helpen je graag verder.</p>

{{venue_block}}

<p style="margin:26px 0 0;">Met vriendelijke groet,<br><strong>{{tenant_name}}</strong></p>
HTML,
            ],
        ];
    }

    /** Klantmails in het Frans (vous). */
    private static function defaultsFr(): array
    {
        $groet = '<p style="margin:26px 0 0;">À bientôt !<br><strong>{{tenant_name}}</strong></p>';

        return [
            'reservation-confirmation-customer' => [
                'subject'   => 'Votre réservation chez {{tenant_name}} – {{event_date_long}}',
                'variables' => self::VARIABLES,
                'body'      => <<<HTML
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Bonjour {{first_name}},</p>
<p>Super, vous venez nous rendre visite ! Nous avons bien reçu votre réservation. Vous trouverez tous les détails ci-dessous.</p>

{{when_block}}

{{details_block}}

{{comment_block}}

{{buttons_block}}

<p style="font-size:13px;color:#64748b;margin:4px 0 0;">{{cancellation_note}} Le rendez-vous est aussi joint à ce mail, pour l'ajouter à votre agenda en un clic.</p>

{{venue_block}}

<p>Une question ou quelque chose à nous signaler ? Répondez simplement à ce mail ou appelez-nous au {{tenant_phone}}.</p>
{$groet}
HTML,
            ],
            'reservation-pending-customer' => [
                'subject'   => 'Nous examinons votre demande – {{tenant_name}}',
                'variables' => self::VARIABLES,
                'body'      => <<<HTML
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Bonjour {{first_name}},</p>
<p>Merci pour votre demande ! Vous avez choisi un moment en dehors de nos heures d'ouverture habituelles, nous vérifions donc d'abord si c'est possible. Nous revenons vers vous au plus vite.</p>

{{when_block}}

{{details_block}}

{{comment_block}}

{{request_buttons_block}}

{{venue_block}}

<p>Des questions ? Répondez simplement à ce mail ou appelez-nous au {{tenant_phone}}.</p>
<p style="margin:26px 0 0;">Bien cordialement,<br><strong>{{tenant_name}}</strong></p>
HTML,
            ],
            'reservation-updated-customer' => [
                'subject'   => 'Votre réservation a été modifiée – {{event_date_long}}',
                'variables' => self::VARIABLES,
                'body'      => <<<HTML
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Bonjour {{first_name}},</p>
<p>Votre modification a bien été enregistrée. Voici les nouvelles données de votre réservation.</p>

{{when_block}}

{{details_block}}

{{comment_block}}

{{buttons_block}}

<p style="font-size:13px;color:#64748b;margin:4px 0 0;">{{cancellation_note}}</p>

{{venue_block}}
{$groet}
HTML,
            ],
            'reservation-cancelled-customer' => [
                'subject'   => 'Votre réservation a été annulée – {{tenant_name}}',
                'variables' => self::VARIABLES,
                'body'      => <<<'HTML'
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Bonjour {{first_name}},</p>
<p>Votre réservation pour <strong>{{event_type}}</strong> le <strong>{{event_date_long}}</strong> à <strong>{{event_time}}</strong> a été annulée.</p>
<p>Dommage que cela ne soit pas possible. Vous souhaitez choisir une nouvelle date, ou s'agissait-il d'une erreur ? N'hésitez pas à nous le faire savoir, nous vous aiderons volontiers.</p>

{{venue_block}}

<p style="margin:26px 0 0;">Bien cordialement,<br><strong>{{tenant_name}}</strong></p>
HTML,
            ],
        ];
    }

    /** Klantmails in het Engels (Brits). */
    private static function defaultsEn(): array
    {
        $groet = '<p style="margin:26px 0 0;">See you soon!<br><strong>{{tenant_name}}</strong></p>';

        return [
            'reservation-confirmation-customer' => [
                'subject'   => 'Your booking at {{tenant_name}} – {{event_date_long}}',
                'variables' => self::VARIABLES,
                'body'      => <<<HTML
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Hi {{first_name}},</p>
<p>Great to hear you're coming! We've received your booking. You'll find all the details below.</p>

{{when_block}}

{{details_block}}

{{comment_block}}

{{buttons_block}}

<p style="font-size:13px;color:#64748b;margin:4px 0 0;">{{cancellation_note}} The appointment is also attached to this email, so you can add it to your calendar in one click.</p>

{{venue_block}}

<p>Questions, or something to let us know? Just reply to this email or call us on {{tenant_phone}}.</p>
{$groet}
HTML,
            ],
            'reservation-pending-customer' => [
                'subject'   => "We're looking at your request – {{tenant_name}}",
                'variables' => self::VARIABLES,
                'body'      => <<<HTML
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Hi {{first_name}},</p>
<p>Thank you for your request! You chose a time outside our usual opening hours, so we'll first check whether it's possible. You'll hear from us as soon as possible.</p>

{{when_block}}

{{details_block}}

{{comment_block}}

{{request_buttons_block}}

{{venue_block}}

<p>Questions? Just reply to this email or call us on {{tenant_phone}}.</p>
<p style="margin:26px 0 0;">Kind regards,<br><strong>{{tenant_name}}</strong></p>
HTML,
            ],
            'reservation-updated-customer' => [
                'subject'   => 'Your booking has been changed – {{event_date_long}}',
                'variables' => self::VARIABLES,
                'body'      => <<<HTML
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Hi {{first_name}},</p>
<p>Your change has come through. These are the new details of your booking.</p>

{{when_block}}

{{details_block}}

{{comment_block}}

{{buttons_block}}

<p style="font-size:13px;color:#64748b;margin:4px 0 0;">{{cancellation_note}}</p>

{{venue_block}}
{$groet}
HTML,
            ],
            'reservation-cancelled-customer' => [
                'subject'   => 'Your booking has been cancelled – {{tenant_name}}',
                'variables' => self::VARIABLES,
                'body'      => <<<'HTML'
<p style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 6px;">Hi {{first_name}},</p>
<p>Your booking for <strong>{{event_type}}</strong> on <strong>{{event_date_long}}</strong> at <strong>{{event_time}}</strong> has been cancelled.</p>
<p>Sorry it didn't work out. Would you like to choose a new date, or was this a mistake? Just let us know, we're happy to help.</p>

{{venue_block}}

<p style="margin:26px 0 0;">Kind regards,<br><strong>{{tenant_name}}</strong></p>
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
