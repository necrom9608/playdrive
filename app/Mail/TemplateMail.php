<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generieke Mailable die een al-gerenderde HTML-body en onderwerp ontvangt.
 * Gebruikt door het template-systeem zodat elke mail-type via dezelfde
 * Mailable verstuurd kan worden.
 */
class TemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $bodyHtml,
        public string $tenantName = 'PlayDrive',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.template-mail');
    }
}
