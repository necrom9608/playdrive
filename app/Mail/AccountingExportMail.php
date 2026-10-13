<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Dagtotalen naar de boekhouding: samenvatting in de mail + Excel als bijlage.
 * Gebruikt dezelfde opmaak als de andere PlayDrive-mails (emails.template-mail).
 */
class AccountingExportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $bodyHtml,
        public string $tenantName,
        public string $attachmentName,
        public string $attachmentData,
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
        return [
            Attachment::fromData(fn () => $this->attachmentData, $this->attachmentName)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
