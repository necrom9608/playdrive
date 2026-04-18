<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PhysicalCard extends Model
{
    public const STATUS_STOCK         = 'stock';
    public const STATUS_IN_CIRCULATION = 'in_circulation';
    public const STATUS_RETURNED      = 'returned';
    public const STATUS_BLOCKED       = 'blocked';
    public const STATUS_RETIRED       = 'retired';

    public const TYPE_VOUCHER = 'voucher';
    public const TYPE_STAFF   = 'staff';
    public const TYPE_MEMBER  = 'member';

    protected $fillable = [
        'tenant_id',
        'card_type',
        'badge_template_id',
        'holder_type',
        'holder_id',
        'voucher_template_id',
        'current_gift_voucher_id',
        'last_gift_voucher_id',
        'label',
        'internal_reference',
        'rfid_uid',
        'status',
        'notes',
        'render_image_path',
        'printed_at',
        'issued_at',
        'returned_at',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $card): void {
            if ($card->card_type !== self::TYPE_MEMBER) {
                return;
            }
            if (! empty($card->rfid_uid)) {
                return;
            }
            $tenantId = (int) ($card->tenant_id ?? 0);
            $holderId = (int) ($card->holder_id ?? 0);
            $card->rfid_uid = sprintf('TMP-MEMBER-%d-%d-%s', $tenantId, $holderId, Str::upper(Str::random(8)));
        });
    }

    protected function casts(): array
    {
        return [
            'printed_at'  => 'datetime',
            'issued_at'   => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_STOCK          => 'Op stock',
            self::STATUS_IN_CIRCULATION => 'In omloop',
            self::STATUS_RETURNED       => 'Teruggebracht',
            self::STATUS_BLOCKED        => 'Geblokkeerd',
            self::STATUS_RETIRED        => 'Buiten gebruik',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_VOUCHER => 'Voucher',
            self::TYPE_STAFF   => 'Staff',
            self::TYPE_MEMBER  => 'Member',
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

    public function badgeTemplate(): BelongsTo
    {
        return $this->belongsTo(BadgeTemplate::class);
    }

    public function currentGiftVoucher(): BelongsTo
    {
        return $this->belongsTo(GiftVoucher::class, 'current_gift_voucher_id');
    }

    public function lastGiftVoucher(): BelongsTo
    {
        return $this->belongsTo(GiftVoucher::class, 'last_gift_voucher_id');
    }

    public function staffHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'holder_id');
    }

    /**
     * Voor member-kaarten: holder_id verwijst naar tenant_memberships.id.
     * Via de eager-loaded account krijgen we naam/email.
     */
    public function memberHolder(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'holder_id');
    }
}
