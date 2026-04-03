<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_WALK_IN = 'walk_in';
    public const SOURCE_RESERVATION = 'reservation';

    protected $fillable = [
        'tenant_id',
        'registration_id',
        'status',
        'source',
        'subtotal_excl_vat',
        'total_vat',
        'total_incl_vat',
        'payment_method',
        'invoice_requested',
        'invoice_exported_at',
        'paid_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'refunded_at',
        'refunded_by',
        'refund_amount',
        'refund_method',
        'refund_reason',
        'created_by',
        'updated_by',
        'paid_by',
        'notes',
        'source',
        'source_reference',
    ];

    protected $casts = [
        'subtotal_excl_vat' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_incl_vat' => 'decimal:2',
        'invoice_requested' => 'boolean',
        'invoice_exported_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'refund_amount' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
