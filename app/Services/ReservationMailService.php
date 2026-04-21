<?php

namespace App\Services;

use App\Mail\TemplateMail;
use App\Models\Registration;
use App\Models\RegistrationAccessToken;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ReservationMailService
{
    /**
     * Verstuurt de bevestigingsmail naar de klant en de notificatiemail
     * naar de tenant. Logt beide mails via MailLogger.
     */
    public static function sendAfterSubmission(Registration $registration, Tenant $tenant): void
    {
        $registration->loadMissing([
            'eventType:id,name,emoji',
            'stayOption:id,name',
            'cateringOption:id,name',
        ]);

        // Access token aanmaken voor magic link (geldig tot 30 dagen na het event,
        // of 90 dagen na aanmaak als er geen datum is)
        $expiresAt = $registration->event_date
            ? $registration->event_date->addDays(30)
            : now()->addDays(90);

        $tokenRecord = RegistrationAccessToken::query()->create([
            'registration_id' => $registration->id,
            'token'           => Str::random(48),
            'expires_at'      => $expiresAt,
        ]);

        $accessUrl = url('/reservatie/' . $tokenRecord->token);

        // Gedeelde variabelen
        $sharedVars = [
            'name'                   => $registration->name,
            'email'                  => $registration->email ?? '—',
            'phone'                  => $registration->phone ?? '—',
            'event_date'             => $registration->event_date?->format('d/m/Y') ?? '—',
            'event_time'             => $registration->event_time
                                            ? substr((string) $registration->event_time, 0, 5)
                                            : '—',
            'event_type'             => $registration->eventType?->name ?? '—',
            'stay_option'            => $registration->stayOption?->name ?? '—',
            'catering_option'        => $registration->cateringOption?->name ?? '—',
            'participants_children'  => (string) $registration->participants_children,
            'participants_adults'    => (string) $registration->participants_adults,
            'participants_supervisors' => (string) $registration->participants_supervisors,
            'participants_total'     => (string) $registration->total_participants,
            'tenant_name'            => $tenant->display_name,
            'tenant_email'           => $tenant->email ?? '',
            'tenant_phone'           => $tenant->phone ?? '',
            'access_url'             => $accessUrl,
        ];

        // ── Klantmail ─────────────────────────────────────────────────────────
        if (filled($registration->email)) {
            $customerTemplate = EmailTemplateResolver::resolve(
                'reservation-confirmation-customer',
                $tenant
            );
            $customerRendered = EmailTemplateResolver::render($customerTemplate, $sharedVars);

            $customerMail = new TemplateMail(
                mailSubject: $customerRendered['subject'],
                bodyHtml:    $customerRendered['body'],
                tenantName:  $tenant->display_name,
            );

            Mail::to($registration->email, $registration->name)
                ->send($customerMail);

            MailLogger::log(
                toEmail:  $registration->email,
                subject:  $customerRendered['subject'],
                toName:   $registration->name,
                tenantId: $tenant->id,
                mailType: 'reservation-confirmation-customer',
                htmlBody: $customerRendered['body'],
            );
        }

        // ── Tenantmail ────────────────────────────────────────────────────────
        if (filled($tenant->email)) {
            // Optionele blokken die in de body kunnen zitten
            $commentBlock = filled($registration->comment)
                ? '<p><strong>Opmerking van de klant:</strong><br>' . e($registration->comment) . '</p>'
                : '';

            $outsideHoursBlock = $registration->outside_opening_hours
                ? '<p style="color:#b45309;background:#fef3c7;padding:10px 14px;border-radius:6px;font-size:13px;">⚠️ <strong>Buiten openingsuren</strong> – deze reservatie valt buiten de normale openingstijden.</p>'
                : '';

            $tenantVars = array_merge($sharedVars, [
                'comment_block'       => $commentBlock,
                'outside_hours_block' => $outsideHoursBlock,
            ]);

            $tenantTemplate = EmailTemplateResolver::resolve(
                'reservation-notification-tenant',
                $tenant
            );
            $tenantRendered = EmailTemplateResolver::render($tenantTemplate, $tenantVars);

            $tenantMail = new TemplateMail(
                mailSubject: $tenantRendered['subject'],
                bodyHtml:    $tenantRendered['body'],
                tenantName:  $tenant->display_name,
            );

            Mail::to($tenant->email, $tenant->display_name)
                ->send($tenantMail);

            MailLogger::log(
                toEmail:  $tenant->email,
                subject:  $tenantRendered['subject'],
                toName:   $tenant->display_name,
                tenantId: $tenant->id,
                mailType: 'reservation-notification-tenant',
                htmlBody: $tenantRendered['body'],
            );
        }
    }
    /**
     * Bevestigingsmail na aanpassing van aantallen/commentaar door de klant.
     */
    public static function sendAfterUpdate(Registration $registration, Tenant $tenant): void
    {
        if (! filled($registration->email)) return;

        $registration->loadMissing([
            'eventType:id,name,emoji',
            'stayOption:id,name',
        ]);

        $accessToken = RegistrationAccessToken::query()
            ->where('registration_id', $registration->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        $accessUrl = $accessToken
            ? url('/reservatie/' . $accessToken->token)
            : url('/');

        $vars = [
            'name'               => $registration->name,
            'event_date'         => $registration->event_date?->format('d/m/Y') ?? '—',
            'event_time'         => $registration->event_time
                                        ? substr((string) $registration->event_time, 0, 5)
                                        : '—',
            'event_type'         => $registration->eventType?->name ?? '—',
            'stay_option'        => $registration->stayOption?->name ?? '—',
            'participants_total' => (string) $registration->total_participants,
            'tenant_name'        => $tenant->display_name,
            'tenant_email'       => $tenant->email ?? '',
            'tenant_phone'       => $tenant->phone ?? '',
            'access_url'         => $accessUrl,
        ];

        $template = EmailTemplateResolver::resolve('reservation-updated-customer', $tenant);
        $rendered = EmailTemplateResolver::render($template, $vars);

        $mail = new TemplateMail(
            mailSubject: $rendered['subject'],
            bodyHtml:    $rendered['body'],
            tenantName:  $tenant->display_name,
        );

        Mail::to($registration->email, $registration->name)->send($mail);

        MailLogger::log(
            toEmail:  $registration->email,
            subject:  $rendered['subject'],
            toName:   $registration->name,
            tenantId: $tenant->id,
            mailType: 'reservation-updated-customer',
            htmlBody: $rendered['body'],
        );
    }

    /**
     * Bevestigingsmail na annulering door de klant.
     */
    public static function sendAfterCancellation(Registration $registration, Tenant $tenant): void
    {
        if (! filled($registration->email)) return;

        $registration->loadMissing(['eventType:id,name,emoji']);

        $vars = [
            'name'         => $registration->name,
            'event_date'   => $registration->event_date?->format('d/m/Y') ?? '—',
            'event_time'   => $registration->event_time
                                  ? substr((string) $registration->event_time, 0, 5)
                                  : '—',
            'event_type'   => $registration->eventType?->name ?? '—',
            'tenant_name'  => $tenant->display_name,
            'tenant_email' => $tenant->email ?? '',
            'tenant_phone' => $tenant->phone ?? '',
        ];

        $template = EmailTemplateResolver::resolve('reservation-cancelled-customer', $tenant);
        $rendered = EmailTemplateResolver::render($template, $vars);

        $mail = new TemplateMail(
            mailSubject: $rendered['subject'],
            bodyHtml:    $rendered['body'],
            tenantName:  $tenant->display_name,
        );

        Mail::to($registration->email, $registration->name)->send($mail);

        MailLogger::log(
            toEmail:  $registration->email,
            subject:  $rendered['subject'],
            toName:   $registration->name,
            tenantId: $tenant->id,
            mailType: 'reservation-cancelled-customer',
            htmlBody: $rendered['body'],
        );
    }

}
