<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAssistantMessage extends Model
{
    public const STATUS_NEW = 'new';         // opgehaald, nog geen voorstel
    public const STATUS_DRAFT = 'draft';     // concept klaar, te beoordelen
    public const STATUS_MANUAL = 'manual';   // moet door een medewerker (klacht, annulering, ...)
    public const STATUS_IGNORED = 'ignored'; // automatisch bericht, nieuwsbrief, eigen adres
    public const STATUS_SENT = 'sent';       // beantwoord via Playdrive
    public const STATUS_DONE = 'done';       // afgehandeld zonder te antwoorden vanuit Playdrive
    public const STATUS_ERROR = 'error';     // voorstel opstellen mislukt

    protected $fillable = [
        'tenant_id',
        'uid',
        'uid_validity',
        'message_id',
        'in_reply_to',
        'references',
        'from_email',
        'from_name',
        'reply_to',
        'subject',
        'body_text',
        'received_at',
        'status',
        'ignore_reason',
        'category',
        'confidence',
        'language',
        'summary',
        'needs_human',
        'needs_human_reason',
        'draft_body',
        'draft_original',
        'draft_sources',
        'draft_warnings',
        'drafted_at',
        'draft_tokens',
        'account_id',
        'sent_body',
        'sent_edited',
        'sent_at',
        'sent_by',
        'handled_by',
        'handled_at',
        'error',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'uid' => 'integer',
        'uid_validity' => 'integer',
        'received_at' => 'datetime',
        'confidence' => 'integer',
        'needs_human' => 'boolean',
        'draft_sources' => 'array',
        'draft_warnings' => 'array',
        'drafted_at' => 'datetime',
        'draft_tokens' => 'integer',
        'account_id' => 'integer',
        'sent_edited' => 'boolean',
        'sent_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Adres waar het antwoord naartoe gaat. */
    public function replyAddress(): string
    {
        return $this->reply_to ?: $this->from_email;
    }
}
