<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailLog extends Model
{
    public const STATUS_QUEUED    = 'queued';
    public const STATUS_SENT      = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_OPENED    = 'opened';
    public const STATUS_CLICKED   = 'clicked';
    public const STATUS_BOUNCED   = 'bounced';
    public const STATUS_COMPLAINED = 'complained';
    public const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'tenant_id',
        'to_email',
        'to_name',
        'account_id',
        'subject',
        'mail_type',
        'html_body',
        'resend_id',
        'status',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'bounced_at',
        'complained_at',
        'bounce_type',
        'bounce_description',
    ];

    protected function casts(): array
    {
        return [
            'sent_at'       => 'datetime',
            'delivered_at'  => 'datetime',
            'opened_at'     => 'datetime',
            'clicked_at'    => 'datetime',
            'bounced_at'    => 'datetime',
            'complained_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function hasIssue(): bool
    {
        return in_array($this->status, [self::STATUS_BOUNCED, self::STATUS_COMPLAINED, self::STATUS_FAILED]);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_QUEUED     => 'In wachtrij',
            self::STATUS_SENT       => 'Verstuurd',
            self::STATUS_DELIVERED  => 'Ontvangen',
            self::STATUS_OPENED     => 'Geopend',
            self::STATUS_CLICKED    => 'Geklikt',
            self::STATUS_BOUNCED    => 'Gebounced',
            self::STATUS_COMPLAINED => 'Spam',
            self::STATUS_FAILED     => 'Mislukt',
            default                 => ucfirst($status),
        };
    }
}
