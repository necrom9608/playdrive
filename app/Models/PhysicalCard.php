<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalCard extends Model
{
    public const STATUS_STOCK = 'stock';
    public const STATUS_IN_CIRCULATION = 'in_circulation';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'tenant_id',
        'voucher_template_id',
        'current_gift_voucher_id',
        'last_gift_voucher_id',
        'label',
        'internal_reference',
        'rfid_uid',
        'status',
        'notes',
        'printed_at',
        'issued_at',
        'returned_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
            'issued_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_STOCK => 'Op stock',
            self::STATUS_IN_CIRCULATION => 'In omloop',
            self::STATUS_RETURNED => 'Teruggebracht',
            self::STATUS_BLOCKED => 'Geblokkeerd',
            self::STATUS_RETIRED => 'Buiten gebruik',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function voucherTemplate(): BelongsTo
    {
        return $this->belongsTo(VoucherTemplate::class);
    }

    public function currentGiftVoucher(): BelongsTo
    {
        return $this->belongsTo(GiftVoucher::class, 'current_gift_voucher_id');
    }

    public function lastGiftVoucher(): BelongsTo
    {
        return $this->belongsTo(GiftVoucher::class, 'last_gift_voucher_id');
    }
}
