<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $receipt,
    ) {
    }

    public function envelope(): Envelope
    {
        $tenantName = $this->receipt['meta']['tenant_name'] ?? config('app.name', 'PlayDrive');
        $orderId = $this->receipt['order']['id'] ?? null;

        return new Envelope(
            subject: $orderId
                ? "Bon #{$orderId} - {$tenantName}"
                : "Je bon - {$tenantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.receipt',
        );
    }
}
