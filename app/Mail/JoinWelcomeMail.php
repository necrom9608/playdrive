<?php

namespace App\Mail;

use App\Models\Account;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Welkom na "lid worden" via /join/{venue}: e-mail bevestigen + wachtwoord kiezen.
 */
class JoinWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Account $account,
        public string  $setupUrl,
        public Tenant  $tenant,
        public string  $code,
        public string  $theme = 'standaard',
    ) {}

    public function subjectLine(): string
    {
        return 'Bevestig je e-mailadres en kies je wachtwoord · '.$this->tenant->display_name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.join-welcome',
            with: [
                'account'    => $this->account,
                'setupUrl'   => $this->setupUrl,
                'tenantName' => $this->tenant->display_name,
                'logoUrl'    => $this->tenant->logo_url,
                'code'       => $this->code,
                'accent'     => $this->theme === 'gameinn' ? '#f27724' : '#2563eb',
                'headerBg'   => $this->theme === 'gameinn' ? '#0a0c11' : '#0f172a',
            ],
        );
    }
}
