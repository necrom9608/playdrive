<?php

namespace App\Services;

use App\Models\BookingFormConfig;
use App\Models\Registration;
use App\Models\StayOption;
use App\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Variabelen en kant-en-klare HTML-blokken voor de reservatiemails.
 *
 * Twee soorten placeholders in de templates:
 *   - gewone waarden: {{name}}, {{event_date}}, {{event_date_long}}, …
 *     (alle oude namen blijven bestaan, zodat eigen templates van een tenant
 *     blijven werken)
 *   - blokken: {{when_block}}, {{details_block}}, {{buttons_block}},
 *     {{venue_block}}, {{customer_block}}, {{invoice_block}},
 *     {{comment_block}}, {{outside_hours_block}}, {{cancellation_note}}
 *     → al opgemaakte HTML (inline stijl, werkt in elke mailclient).
 *
 * build() werkt op een gewone array en een tenant-object, zodat de preview
 * in de backoffice exact dezelfde opmaak toont als de echte mail.
 *
 * Taal: $d['locale'] (nl, fr, en; standaard nl). Vaste teksten en de namen van
 * soorten, formules en catering gaan door __() met lang/fr.json en
 * lang/en.json (Nederlandse tekst als sleutel; geen vertaling = Nederlands).
 */
class ReservationMailVars
{
    /** Accentkleur van knoppen en het datumvak. */
    public const ACCENT = '#f27724';

    private const LABEL = 'padding:7px 0;color:#64748b;width:38%;vertical-align:top;font-size:14px;';
    private const VALUE = 'padding:7px 0;color:#0f172a;font-weight:600;vertical-align:top;font-size:14px;';

    // ── Data verzamelen ────────────────────────────────────────────────────

    /** @return array{vars: array<string,string>, ics: ?string} */
    public static function forRegistration(Registration $r, Tenant $tenant, ?string $accessUrl = null, ?string $locale = null): array
    {
        $r->loadMissing(['eventType:id,name', 'cateringOption:id,name']);
        $stay = $r->stay_option_id
            ? StayOption::query()->select(['id', 'name', 'duration_minutes'])->find($r->stay_option_id)
            : null;

        $data = [
            'id'            => $r->id,
            'name'          => (string) $r->name,
            'email'         => $r->email,
            'phone'         => $r->phone,
            'postal_code'   => $r->postal_code,
            'municipality'  => $r->municipality,
            'date'          => $r->event_date,
            'time'          => $r->event_time ? substr((string) $r->event_time, 0, 5) : null,
            'duration'      => $stay?->duration_minutes,
            'event_type'    => $r->eventType?->name,
            'stay_option'   => $stay?->name,
            'catering'      => $r->cateringOption?->name,
            'children'      => (int) $r->participants_children,
            'adults'        => (int) $r->participants_adults,
            'supervisors'   => (int) $r->participants_supervisors,
            'comment'       => $r->comment,
            'outside_hours' => (bool) $r->outside_opening_hours,
            'invoice'       => $r->invoice_requested ? [
                'company'     => $r->invoice_company_name,
                'vat'         => $r->invoice_vat_number,
                'email'       => $r->invoice_email,
                'address'     => $r->invoice_address,
                'postal_code' => $r->invoice_postal_code,
                'city'        => $r->invoice_city,
            ] : null,
            'access_url'    => $accessUrl,
            'cancel_hours'  => BookingFormConfig::query()->where('tenant_id', $tenant->id)->value('cancellation_hours_before'),
            'locale'        => self::taal($locale ?? $r->locale),
        ];

        return ['vars' => self::build($data, $tenant), 'ics' => self::ics($data, $tenant)];
    }

    /** Voorbeelddata voor de preview in de backoffice. */
    public static function sample(object $tenant): array
    {
        return self::build([
            'id'            => 0,
            'name'          => 'Jan Peeters',
            'email'         => 'jan@voorbeeld.be',
            'phone'         => '+32 478 12 34 56',
            'postal_code'   => '8790',
            'municipality'  => 'Waregem',
            'date'          => Carbon::now()->addDays(14)->setTime(12, 0),
            'time'          => '14:00',
            'duration'      => 120,
            'event_type'    => 'Verjaardagsfeest',
            'stay_option'   => '2 uur',
            'catering'      => 'Pannenkoeken',
            'children'      => 8,
            'adults'        => 2,
            'supervisors'   => 2,
            'comment'       => 'Graag een kaarsje op de pannenkoek van de jarige.',
            'outside_hours' => false,
            'invoice'       => null,
            'access_url'    => url('/reservatie/voorbeeld-token-abc123'),
            'cancel_hours'  => 48,
        ], $tenant);
    }

    // ── Taal ───────────────────────────────────────────────────────────────

    public const TALEN = ['nl', 'fr', 'en'];

    public static function taal(?string $locale): string
    {
        return in_array($locale, self::TALEN, true) ? $locale : 'nl';
    }

    /** Vertaling in de taal van de mail die nu opgebouwd wordt. */
    private static string $taal = 'nl';

    private static function t(?string $tekst, array $vars = []): string
    {
        if ($tekst === null || $tekst === '') return (string) $tekst;
        if (self::$taal === 'nl') {
            // Nederlands = de tekst zelf (lang/nl.json heeft Engelse sleutels)
            foreach ($vars as $k => $v) $tekst = str_replace(':' . $k, (string) $v, $tekst);

            return $tekst;
        }

        return (string) __($tekst, $vars, self::$taal);
    }

    // ── Variabelen en blokken ──────────────────────────────────────────────

    /** @return array<string,string> */
    public static function build(array $d, object $t): array
    {
        self::$taal = self::taal($d['locale'] ?? null);
        // Namen uit PlayDrive (soort, formule, catering) in de taal van de mail
        foreach (['event_type', 'stay_option', 'catering'] as $k) {
            if (filled($d[$k] ?? null)) $d[$k] = self::t($d[$k]);
        }
        $date  = $d['date'] instanceof CarbonInterface ? $d['date']->copy()->locale(self::$taal) : null;
        $time  = $d['time'] ?: null;
        $eind  = ($date && $time && $d['duration']) ? Carbon::parse($date->format('Y-m-d') . ' ' . $time)->addMinutes((int) $d['duration'])->format('H:i') : null;
        $periode = $time ? ($eind ? "{$time} – {$eind}" : self::t('vanaf :uur', ['uur' => $time])) : '—';
        $dagLang = $date ? $date->translatedFormat('l j F Y') : '—'; // "zaterdag 24 oktober 2026"

        $tenantName = (string) ($t->display_name ?? 'PlayDrive');
        $adres      = $t->full_address ?? null;
        $mapsUrl    = $adres ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($tenantName . ', ' . $adres) : '';
        $agendaUrl  = self::googleCalendarUrl($d, $t, $eind);
        $voornaam   = trim(strtok((string) $d['name'], ' ') ?: (string) $d['name']);

        $vars = [
            // oude namen (blijven werken in eigen templates)
            'name'                     => (string) $d['name'],
            'email'                    => $d['email'] ?: '—',
            'phone'                    => $d['phone'] ?: '—',
            'event_date'               => $date ? $date->format('d/m/Y') : '—',
            'event_time'               => $time ?? '—',
            'event_type'               => $d['event_type'] ?: '—',
            'stay_option'              => $d['stay_option'] ?: '—',
            'catering_option'          => $d['catering'] ?: '—',
            'participants_children'    => (string) $d['children'],
            'participants_adults'      => (string) $d['adults'],
            'participants_supervisors' => (string) $d['supervisors'],
            'participants_total'       => (string) ($d['children'] + $d['adults'] + $d['supervisors']),
            'tenant_name'              => $tenantName,
            'tenant_email'             => (string) ($t->email ?? ''),
            'tenant_phone'             => (string) ($t->phone ?? ''),
            'access_url'               => (string) ($d['access_url'] ?? ''),
            // nieuw
            'first_name'               => $voornaam,
            'event_date_long'          => $dagLang,
            'event_time_end'           => $eind ?? '',
            'event_period'             => $periode,
            'participants_summary'     => self::deelnemers($d),
            'comment'                  => e((string) ($d['comment'] ?? '')),
            'tenant_address'           => (string) ($adres ?? ''),
            'tenant_website'           => (string) ($t->website_url ?? ''),
            'maps_url'                 => $mapsUrl,
            'calendar_url'             => $agendaUrl,
        ];

        $vars['when_block']          = self::whenBlock($d, $dagLang, $periode);
        $vars['details_block']       = self::detailsBlock($d);
        $vars['buttons_block']       = self::buttonsBlock($d['access_url'] ?? null, self::t('Bekijk of wijzig je reservatie'), $agendaUrl, $mapsUrl);
        $vars['request_buttons_block']  = self::buttonsBlock($d['access_url'] ?? null, self::t('Bekijk je aanvraag'), '', $mapsUrl);
        $vars['venue_block']         = self::venueBlock($t, $mapsUrl);
        $vars['customer_block']      = self::customerBlock($d);
        $vars['invoice_block']       = self::invoiceBlock($d['invoice'] ?? null);
        $vars['comment_block']       = filled($d['comment'] ?? null)
            ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;"><tr><td style="padding:14px 18px;background:#f8fafc;border-left:3px solid ' . self::ACCENT . ';border-radius:8px;font-size:14px;color:#334155;"><strong style="color:#0f172a;">' . e(self::t('Opmerking')) . '</strong><br>' . nl2br(e((string) $d['comment'])) . '</td></tr></table>'
            : '';
        $vars['outside_hours_block'] = ! empty($d['outside_hours'])
            ? '<p style="color:#92400e;background:#fef3c7;padding:12px 16px;border-radius:8px;font-size:14px;margin:18px 0;"><strong>' . e(self::t('Buiten de openingsuren')) . '</strong> – ' . e(self::t('deze aanvraag valt buiten de normale openingstijden (privéboeking).')) . '</p>'
            : '';
        $vars['cancellation_note']   = ! empty($d['cancel_hours'])
            ? self::t('Wijzigen of annuleren kan zelf, tot :uren uur op voorhand, via de knop hierboven. Je hebt geen account nodig.', ['uren' => (int) $d['cancel_hours']])
            : self::t('Via de knop hierboven kan je je reservatie altijd bekijken. Je hebt geen account nodig.');

        return $vars;
    }

    private static function deelnemers(array $d): string
    {
        $delen = [];
        if ($d['children'])    $delen[] = $d['children'] . ' ' . self::t($d['children'] === 1 ? 'kind' : 'kinderen');
        if ($d['adults'])      $delen[] = $d['adults'] . ' ' . self::t($d['adults'] === 1 ? 'volwassene' : 'volwassenen');
        if ($d['supervisors']) $delen[] = $d['supervisors'] . ' ' . self::t($d['supervisors'] === 1 ? 'begeleider' : 'begeleiders');

        return $delen ? implode(' · ', $delen) : '—';
    }

    private static function whenBlock(array $d, string $dagLang, string $periode): string
    {
        $soort = e((string) ($d['event_type'] ?: self::t('Reservatie')));
        $extra = filled($d['stay_option'] ?? null) ? ' &middot; ' . e((string) $d['stay_option']) : '';

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0 8px;border-collapse:separate;">'
            . '<tr><td style="padding:18px 22px;background:#fff7f0;border:1px solid #fcd9bd;border-radius:14px;">'
            . '<div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:' . self::ACCENT . ';font-weight:700;">' . $soort . '</div>'
            . '<div style="font-size:22px;line-height:1.25;font-weight:800;color:#0f172a;margin-top:6px;">' . e(mb_strtoupper(mb_substr($dagLang, 0, 1)) . mb_substr($dagLang, 1)) . '</div>'
            . '<div style="font-size:16px;color:#334155;margin-top:4px;">' . e($periode) . $extra . '</div>'
            . '</td></tr></table>';
    }

    private static function rij(string $label, string $waarde): string
    {
        return '<tr><td style="' . self::LABEL . '">' . e($label) . '</td><td style="' . self::VALUE . '">' . $waarde . '</td></tr>';
    }

    private static function detailsBlock(array $d): string
    {
        $rijen = '';
        if (filled($d['event_type'] ?? null))  $rijen .= self::rij(self::t('Wat'), e((string) $d['event_type']));
        if (filled($d['stay_option'] ?? null)) $rijen .= self::rij(self::t('Formule'), e((string) $d['stay_option']));
        if (filled($d['catering'] ?? null))    $rijen .= self::rij(self::t('Catering'), e((string) $d['catering']));
        $rijen .= self::rij(self::t('Deelnemers'), e(self::deelnemers($d)));

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:14px 0 6px;">' . $rijen . '</table>';
    }

    private static function knop(string $url, string $tekst, bool $hoofd): string
    {
        $stijl = $hoofd
            ? 'background:' . self::ACCENT . ';color:#ffffff;border:1px solid ' . self::ACCENT . ';'
            : 'background:#ffffff;color:#0f172a;border:1px solid #cbd5e1;';

        return '<a href="' . e($url) . '" style="display:inline-block;' . $stijl . 'text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:700;font-size:14px;margin:0 8px 10px 0;">' . e($tekst) . '</a>';
    }

    private static function buttonsBlock(?string $toegang, string $label, string $agenda, string $maps): string
    {
        $knoppen = '';
        if ($toegang) $knoppen .= self::knop($toegang, $label, true);
        if ($agenda)  $knoppen .= self::knop($agenda, self::t('Zet in je agenda'), false);
        if ($maps)    $knoppen .= self::knop($maps, self::t('Route'), false);

        return $knoppen ? '<div style="margin:22px 0 4px;">' . $knoppen . '</div>' : '';
    }

    private static function venueBlock(object $t, string $maps): string
    {
        $regels = ['<strong style="color:#0f172a;font-size:15px;">' . e((string) ($t->display_name ?? '')) . '</strong>'];
        if (filled($t->full_address ?? null)) {
            $regels[] = $maps ? '<a href="' . e($maps) . '" style="color:#334155;text-decoration:none;">' . e($t->full_address) . '</a>' : e($t->full_address);
        }
        $contact = [];
        if (filled($t->phone ?? null)) $contact[] = '<a href="tel:' . e(preg_replace('/[^\d+]/', '', (string) $t->phone)) . '" style="color:#334155;text-decoration:none;">' . e($t->phone) . '</a>';
        if (filled($t->email ?? null)) $contact[] = '<a href="mailto:' . e($t->email) . '" style="color:' . self::ACCENT . ';text-decoration:none;">' . e($t->email) . '</a>';
        if ($contact) $regels[] = implode(' &middot; ', $contact);
        if (filled($t->website_url ?? null)) {
            $regels[] = '<a href="' . e($t->website_url) . '" style="color:' . self::ACCENT . ';text-decoration:none;">' . e(preg_replace('#^https?://(www\.)?#', '', rtrim((string) $t->website_url, '/'))) . '</a>';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0 8px;"><tr><td style="padding:16px 20px;background:#f8fafc;border-radius:12px;font-size:14px;line-height:1.7;color:#334155;">'
            . implode('<br>', $regels) . '</td></tr></table>';
    }

    private static function customerBlock(array $d): string
    {
        $rijen = self::rij(self::t('Naam'), e((string) $d['name']));
        if (filled($d['email'] ?? null)) $rijen .= self::rij(self::t('E-mail'), '<a href="mailto:' . e($d['email']) . '" style="color:' . self::ACCENT . ';">' . e($d['email']) . '</a>');
        if (filled($d['phone'] ?? null)) $rijen .= self::rij(self::t('Telefoon'), '<a href="tel:' . e(preg_replace('/[^\d+]/', '', (string) $d['phone'])) . '" style="color:#0f172a;">' . e($d['phone']) . '</a>');
        $plaats = trim(($d['postal_code'] ?? '') . ' ' . ($d['municipality'] ?? ''));
        if ($plaats !== '') $rijen .= self::rij(self::t('Woonplaats'), e($plaats));

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:14px 0 6px;">' . $rijen . '</table>';
    }

    private static function invoiceBlock(?array $f): string
    {
        if (! $f) return '';
        $rijen = '';
        if (filled($f['company'] ?? null)) $rijen .= self::rij(self::t('Naam / bedrijf'), e($f['company']));
        if (filled($f['vat'] ?? null))     $rijen .= self::rij(self::t('Btw-nummer'), e($f['vat']));
        $adres = trim(implode(', ', array_filter([$f['address'] ?? null, trim(($f['postal_code'] ?? '') . ' ' . ($f['city'] ?? ''))])));
        if ($adres !== '')                 $rijen .= self::rij(self::t('Adres'), e($adres));
        if (filled($f['email'] ?? null))   $rijen .= self::rij(self::t('E-mail factuur'), e($f['email']));

        return '<p style="margin:20px 0 0;font-weight:700;color:#0f172a;">' . e(self::t('Factuur gevraagd')) . '</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:6px 0;">' . $rijen . '</table>';
    }

    // ── Agenda ─────────────────────────────────────────────────────────────

    private static function tijden(array $d): ?array
    {
        if (! ($d['date'] instanceof CarbonInterface) || ! $d['time']) return null;
        $start = Carbon::parse($d['date']->format('Y-m-d') . ' ' . $d['time'], 'Europe/Brussels');
        $eind  = $start->copy()->addMinutes((int) ($d['duration'] ?: 120));

        return [$start, $eind];
    }

    private static function googleCalendarUrl(array $d, object $t, ?string $eind): string
    {
        if (! $tijden = self::tijden($d)) return '';
        [$start, $einde] = $tijden;

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => trim(($d['event_type'] ?: self::t('Reservatie')) . ' – ' . ($t->display_name ?? '')),
            'dates'    => $start->format('Ymd\THis') . '/' . $einde->format('Ymd\THis'),
            'ctz'      => 'Europe/Brussels',
            'location' => trim(($t->display_name ?? '') . ', ' . ($t->full_address ?? ''), ', '),
            'details'  => ($d['access_url'] ?? null) ? self::t('Je reservatie: :url', ['url' => $d['access_url']]) : '',
        ]);
    }

    /** .ics-bijlage (werkt in Outlook, Apple Agenda en Google). */
    public static function ics(array $d, object $t): ?string
    {
        if (! $tijden = self::tijden($d)) return null;
        [$start, $einde] = $tijden;
        $esc = fn (string $s) => str_replace(["\\", ';', ',', "\n"], ["\\\\", '\;', '\,', '\n'], $s);
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'playdrive';

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//PlayDrive//Reservatie//NL',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:reservatie-' . ($d['id'] ?? 0) . '@' . $host,
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:' . $start->copy()->utc()->format('Ymd\THis\Z'), // UTC: geen VTIMEZONE nodig
            'DTEND:' . $einde->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:' . $esc(trim(self::t($d['event_type'] ?: 'Reservatie') . ' – ' . ($t->display_name ?? ''))),
            'LOCATION:' . $esc(trim(($t->display_name ?? '') . ', ' . ($t->full_address ?? ''), ', ')),
            'DESCRIPTION:' . $esc(($d['access_url'] ?? null) ? self::t('Je reservatie bekijken of wijzigen: :url', ['url' => $d['access_url']]) : ''),
            'END:VEVENT',
            'END:VCALENDAR',
        ]) . "\r\n";
    }
}
