<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generieke Mailable die een al-gerenderde HTML-body en onderwerp ontvangt.
 * Gebruikt door het template-systeem zodat elke mail-type via dezelfde
 * Mailable verstuurd kan worden.
 *
 * $brand (optioneel): logo, adres, telefoon, e-mail en website van de
 * tenant voor de kop en voet van de mail (zie TemplateMail::brandFor()).
 * $ics (optioneel): agenda-uitnodiging als bijlage (reservatie.ics).
 */
class TemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $bodyHtml,
        public string $tenantName = 'PlayDrive',
        public array $brand = [],
        public ?string $ics = null,
    ) {
    }

    /** Merkgegevens van een tenant voor de layout. */
    public static function brandFor(?object $tenant): array
    {
        if (! $tenant) return [];
        $logo = $tenant->logo_url ?? null;
        if ($logo && str_starts_with($logo, '/')) {
            $logo = url($logo);
        }

        return array_filter([
            'logo'    => $logo,
            'tagline' => $tenant->tagline ?? null,
            'address' => $tenant->full_address ?? null,
            'phone'   => $tenant->phone ?? null,
            'email'   => $tenant->email ?? null,
            'website' => $tenant->website_url ?? null,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.template-mail');
    }

    public function attachments(): array
    {
        return $this->ics
            ? [Attachment::fromData(fn () => $this->ics, 'reservatie.ics')->withMime('text/calendar; charset=utf-8; method=PUBLISH')]
            : [];
    }
}
