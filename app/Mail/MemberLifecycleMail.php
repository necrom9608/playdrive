<?php

namespace App\Mail;

use App\Models\TenantMembership;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberLifecycleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantMembership $membership,
        public string           $type,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->type) {
            'confirmation'   => 'Bevestiging van je abonnement',
            'expiring_14d'   => 'Je abonnement vervalt over 2 weken',
            'expiring_7d'    => 'Je abonnement vervalt over 1 week',
            'expired'        => 'Je abonnement is verlopen',
            default          => 'Update over je abonnement',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-lifecycle',
            with: [
                'membership' => $this->membership,
                'type'       => $this->type,
            ],
        );
    }
}
