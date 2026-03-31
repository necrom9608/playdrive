<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftVoucher extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'tenant_id',
        'code',
        'qr_token',
        'nfc_uid',
        'name',
        'customer_name',
        'customer_email',
        'source_channel',
        'status',
        'amount_initial',
        'amount_remaining',
        'expires_at',
        'validated_at',
        'validated_by',
        'redeemed_at',
        'redeemed_by',
        'applied_order_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_initial' => 'decimal:2',
            'amount_remaining' => 'decimal:2',
            'expires_at' => 'date',
            'validated_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Actief',
            self::STATUS_VALIDATED => 'Gevalideerd',
            self::STATUS_REDEEMED => 'Ingewisseld',
            self::STATUS_CANCELLED => 'Geannuleerd',
            self::STATUS_EXPIRED => 'Vervallen',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'applied_order_id');
    }
}
