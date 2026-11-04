<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mails rond een aankoopborderel: uitnodiging om te ondertekenen, of het ondertekende
 * borderel als PDF-bijlage. Zelfde opmaak als de andere Playdrive-mails.
 */
class PrivatePurchaseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $bodyHtml,
        public string $tenantName,
        public array $brand = [],
        public ?string $pdfName = null,
        public ?string $pdfData = null,
        public ?string $replyToEmail = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
            replyTo: $this->replyToEmail ? [$this->replyToEmail] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.template-mail');
    }

    public function attachments(): array
    {
        if (! $this->pdfName || $this->pdfData === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->pdfData, $this->pdfName)->withMime('application/pdf'),
        ];
    }
}
