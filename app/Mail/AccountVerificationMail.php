<?php

namespace App\Mail;

use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Account $account,
        public string  $verifyUrl,
        public ?string $tenantName = null,
    ) {}

    public function envelope(): Envelope
    {
        // Load subject from email template overrides if available
        $subject = $this->resolveSubject();

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-verification',
            with: [
                'account'    => $this->account,
                'verifyUrl'  => $this->verifyUrl,
                'tenantName' => $this->tenantName,
                'bodyHtml'   => $this->resolveBody(),
            ],
        );
    }

    private function resolveSubject(): string
    {
        $overrides = $this->loadOverrides();
        return $overrides['account-verification']['subject']
            ?? 'Bevestig je e-mailadres — PlayDrive';
    }

    private function resolveBody(): ?string
    {
        $overrides = $this->loadOverrides();

        if (! isset($overrides['account-verification']['body'])) {
            return null; // use default blade template
        }

        $body = $overrides['account-verification']['body'];

        // Replace variables
        $vars = [
            'first_name'  => $this->account->first_name,
            'last_name'   => $this->account->last_name,
            'tenant_name' => $this->tenantName ?? 'PlayDrive',
            'verify_url'  => $this->verifyUrl,
        ];

        foreach ($vars as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return $body;
    }

    private function loadOverrides(): array
    {
        $path = storage_path('app/email-template-overrides.json');

        if (! file_exists($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }
}
