<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'postal_code',
        'municipality',
        'event_type_id',
        'event_date',
        'event_time',
        'stay_option_id',
        'catering_option_id',
        'participants_children',
        'participants_adults',
        'participants_supervisors',
        'comment',
        'stats',
        'status',
        'invoice_requested',
        'invoice_company_name',
        'invoice_vat_number',
        'invoice_email',
        'invoice_address',
        'invoice_postal_code',
        'invoice_city',
        'checked_in_at',
        'checked_out_at',
        'played_minutes',
        'bill_total_cents',
        'outside_opening_hours',
    ];

    protected $casts = [
        'event_date' => 'date',
        'event_time' => 'string',
        'stats' => 'array',
        'invoice_requested' => 'boolean',
        'outside_opening_hours' => 'boolean',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'participants_children' => 'integer',
        'participants_adults' => 'integer',
        'participants_supervisors' => 'integer',
        'played_minutes' => 'integer',
        'bill_total_cents' => 'integer',
    ];

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    public function stayOption(): BelongsTo
    {
        return $this->belongsTo(StayOption::class);
    }

    public function cateringOption(): BelongsTo
    {
        return $this->belongsTo(CateringOption::class);
    }

    public function getTotalParticipantsAttribute(): int
    {
        return (int) $this->participants_children
            + (int) $this->participants_adults
            + (int) $this->participants_supervisors;
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'Nieuw',
            self::STATUS_CONFIRMED => 'Bevestigd',
            self::STATUS_CHECKED_IN => 'Ingecheckt',
            self::STATUS_CHECKED_OUT => 'Uitgecheckt',
            self::STATUS_PAID => 'Betaald',
            self::STATUS_CANCELLED => 'Geannuleerd',
            self::STATUS_NO_SHOW => 'No-show',
        ];
    }
}
