<?php

namespace App\Mail;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberLifecycleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public string $type,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->type) {
            'confirmation' => 'Bevestiging van je abonnement',
            'expiring' => 'Je abonnement vervalt binnenkort',
            'expired' => 'Je abonnement is vervallen',
            default => 'Update over je abonnement',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-lifecycle',
            with: [
                'member' => $this->member,
                'type' => $this->type,
            ],
        );
    }
}
