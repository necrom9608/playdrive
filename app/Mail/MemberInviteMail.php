<?php

namespace App\Mail;

use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Account $account,
        public string  $resetUrl,
        public ?string $tenantName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Activeer je PlayDrive account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-invite',
            with: [
                'account'    => $this->account,
                'resetUrl'   => $this->resetUrl,
                'tenantName' => $this->tenantName,
            ],
        );
    }
}
