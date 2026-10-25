<?php

namespace App\Services;

use App\Mail\TemplateMail;
use App\Models\Registration;
use App\Models\RegistrationAccessToken;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reservatiemails naar de klant en naar de zaak.
 *
 * Alle mails krijgen dezelfde, uitgebreide set variabelen en blokken
 * (ReservationMailVars) en de merkgegevens van de tenant (logo, adres, …).
 * Een mail die niet vertrekt wordt gelogd maar breekt de reservatie nooit af.
 *
 * Taal: mails naar de klant in $registration->locale (nl, fr, en), de
 * melding naar de zaak altijd in het Nederlands.
 */
class ReservationMailService
{
    /**
     * Na het indienen (of na bevestiging van een aanvraag): bevestiging naar
     * de klant (met agenda-bijlage) en, tenzij \$notifyTenant false is,
     * een melding naar de zaak.
     */
    public static function sendAfterSubmission(Registration $registration, Tenant $tenant, bool $notifyTenant = true): void
    {
        $token = self::newToken($registration);
        $link = url('/reservatie/' . $token);
        $taal = ReservationMailVars::taal($registration->locale);

        if (filled($registration->email)) {
            ['vars' => $vars, 'ics' => $ics] = ReservationMailVars::forRegistration($registration, $tenant, $link, $taal);
            self::send('reservation-confirmation-customer', $tenant, $vars, $registration->email, $registration->name, $ics, $taal);
        }

        if ($notifyTenant && filled($tenant->email)) {
            ['vars' => $vars] = ReservationMailVars::forRegistration($registration, $tenant, $link, 'nl');
            self::send('reservation-notification-tenant', $tenant, $vars, $tenant->email, $tenant->display_name);
        }
    }

    /** Na een aanpassing door de klant. */
    public static function sendAfterUpdate(Registration $registration, Tenant $tenant): void
    {
        if (! filled($registration->email)) return;

        $token = RegistrationAccessToken::query()
            ->where('registration_id', $registration->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        $taal = ReservationMailVars::taal($registration->locale);
        ['vars' => $vars, 'ics' => $ics] = ReservationMailVars::forRegistration(
            $registration, $tenant, $token ? url('/reservatie/' . $token->token) : null, $taal
        );

        self::send('reservation-updated-customer', $tenant, $vars, $registration->email, $registration->name, $ics, $taal);
    }

    /** Na een annulering door de klant. */
    public static function sendAfterCancellation(Registration $registration, Tenant $tenant): void
    {
        if (! filled($registration->email)) return;

        $taal = ReservationMailVars::taal($registration->locale);
        ['vars' => $vars] = ReservationMailVars::forRegistration($registration, $tenant, null, $taal);

        self::send('reservation-cancelled-customer', $tenant, $vars, $registration->email, $registration->name, null, $taal);
    }

    /**
     * Aanvraag buiten de openingsuren (status = pending): "goed ontvangen,
     * we bekijken het". Ook de zaak krijgt een melding.
     */
    public static function sendPendingAcknowledgement(Registration $registration, Tenant $tenant): void
    {
        $token = self::newToken($registration);
        $link = url('/reservatie/' . $token);
        $taal = ReservationMailVars::taal($registration->locale);

        if (filled($registration->email)) {
            ['vars' => $vars] = ReservationMailVars::forRegistration($registration, $tenant, $link, $taal);
            self::send('reservation-pending-customer', $tenant, $vars, $registration->email, $registration->name, null, $taal);
        }

        if (filled($tenant->email)) {
            ['vars' => $vars] = ReservationMailVars::forRegistration($registration, $tenant, $link, 'nl');
            self::send('reservation-notification-tenant', $tenant, $vars, $tenant->email, $tenant->display_name);
        }
    }

    // ── Hulp ──────────────────────────────────────────────────────────────

    /** Magic link: geldig tot 30 dagen na het event, of 90 dagen zonder datum. */
    private static function newToken(Registration $registration): string
    {
        return RegistrationAccessToken::query()->create([
            'registration_id' => $registration->id,
            'token'           => Str::random(48),
            'expires_at'      => $registration->event_date
                ? $registration->event_date->copy()->addDays(30)
                : now()->addDays(90),
        ])->token;
    }

    private static function send(string $key, Tenant $tenant, array $vars, string $to, ?string $toName, ?string $ics = null, string $taal = 'nl'): void
    {
        try {
            $rendered = EmailTemplateResolver::render(EmailTemplateResolver::resolve($key, $tenant, $taal), $vars);

            $mail = new TemplateMail(
                mailSubject: $rendered['subject'],
                bodyHtml:    $rendered['body'],
                tenantName:  $tenant->display_name,
                brand:       TemplateMail::brandFor($tenant),
                ics:         $ics,
            );
            $mail->locale($taal);

            $sent = Mail::to($to, $toName)->send($mail);

            MailLogger::log(
                toEmail:  $to,
                subject:  $rendered['subject'],
                toName:   $toName,
                tenantId: $tenant->id,
                mailType: $key,
                htmlBody: $rendered['body'],
                resendId: self::resendId($sent),
            );
        } catch (Throwable $e) {
            Log::error("Reservatiemail {$key} naar {$to} mislukt", ['tenant' => $tenant->id, 'fout' => $e->getMessage()]);
        }
    }

    /**
     * Resend-id voor de webhook (status geleverd/geopend). Laravels Resend-
     * transport zet die als X-Resend-Email-ID header op het bericht; met een
     * andere mailer is er geen en blijft het leeg.
     */
    private static function resendId($sent): ?string
    {
        try {
            return $sent?->getOriginalMessage()?->getHeaders()?->get('X-Resend-Email-ID')?->getBodyAsString() ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
