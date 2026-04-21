<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Tenant;
class Registration extends Model
{
    use HasFactory;

    public const STATUS_NEW          = 'new';
    public const STATUS_PENDING      = 'pending';
    public const STATUS_CONFIRMED    = 'confirmed';
    public const STATUS_CHECKED_IN   = 'checked_in';
    public const STATUS_CHECKED_OUT  = 'checked_out';
    public const STATUS_PAID         = 'paid';
    public const STATUS_CANCELLED    = 'cancelled';
    public const STATUS_NO_SHOW      = 'no_show';

    protected $fillable = [
        'tenant_id',
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
        'checked_in_by',
        'checked_out_at',
        'checked_out_by',
        'cancelled_at',
        'cancelled_by',
        'no_show_at',
        'no_show_by',
        'created_by',
        'updated_by',
        'played_minutes',
        'bill_total_cents',
        'outside_opening_hours',
        'is_member',
        'member_id',
        'account_id',
    ];

    protected $casts = [
        'event_date'             => 'date',
        'event_time'             => 'string',
        'stats'                  => 'array',
        'invoice_requested'      => 'boolean',
        'outside_opening_hours'  => 'boolean',
        'is_member'              => 'boolean',
        'checked_in_at'          => 'datetime',
        'checked_out_at'         => 'datetime',
        'cancelled_at'           => 'datetime',
        'no_show_at'             => 'datetime',
        'participants_children'  => 'integer',
        'participants_adults'    => 'integer',
        'participants_supervisors' => 'integer',
        'played_minutes'         => 'integer',
        'bill_total_cents'       => 'integer',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW         => 'Nieuw',
            self::STATUS_PENDING     => 'In behandeling',
            self::STATUS_CONFIRMED   => 'Bevestigd',
            self::STATUS_CHECKED_IN  => 'Ingecheckt',
            self::STATUS_CHECKED_OUT => 'Uitgecheckt',
            self::STATUS_PAID        => 'Betaald',
            self::STATUS_CANCELLED   => 'Geannuleerd',
            self::STATUS_NO_SHOW     => 'No-show',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_NEW         => 'Nieuw',
            self::STATUS_PENDING     => 'In behandeling',
            self::STATUS_CONFIRMED   => 'Bevestigd',
            self::STATUS_CHECKED_IN  => 'Ingecheckt',
            self::STATUS_CHECKED_OUT => 'Uitgecheckt',
            self::STATUS_PAID        => 'Betaald',
            self::STATUS_CANCELLED   => 'Geannuleerd',
            self::STATUS_NO_SHOW     => 'No-show',
            default                  => 'Onbekend',
        };
    }

    // ------------------------------------------------------------------
    // Relaties
    // ------------------------------------------------------------------

    /**
     * De persoon (globaal account) gekoppeld aan deze registratie.
     * Vervangt geleidelijk de member() relatie.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Backward compat: lid via de members tabel.
     * Wordt verwijderd zodal de members tabel gedropped wordt (stap 5).
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

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

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function noShowBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'no_show_by');
    }

    public function getTotalParticipantsAttribute(): int
    {
        return (int) $this->participants_children
            + (int) $this->participants_adults
            + (int) $this->participants_supervisors;
    }
}
