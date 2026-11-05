<?php

namespace App\Mail;

use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/** "Je #1 is overgenomen" — gebundeld per venue, max. één per dag (zie arcade:notify-dethrones). */
class ArcadeDethroneMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{title:string, chart:string, game:string, by:string, by_score:?int, your_score:?int}>  $items
     */
    public function __construct(
        public Account $account,
        public string  $venueName,
        public array   $items,
        public string  $statsUrl,
        public string  $unsubscribeUrl,
    ) {}

    public function subjectLine(): string
    {
        $first = $this->items[0] ?? null;
        if (count($this->items) === 1 && $first) {
            return "{$first['by']} nam je #1 over op {$first['title']}";
        }

        return 'Je bent je #1 kwijt op ' . count($this->items) . ' charts bij ' . $this->venueName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<' . $this->unsubscribeUrl . '>',
        ]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.arcade-dethrone',
            with: [
                'account'        => $this->account,
                'venueName'      => $this->venueName,
                'items'          => $this->items,
                'statsUrl'       => $this->statsUrl,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }
}
