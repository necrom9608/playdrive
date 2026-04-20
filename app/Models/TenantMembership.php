<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantMembership extends Model
{
    protected $fillable = [
        'account_id',
        'tenant_id',
        'membership_type',
        'rfid_uid',
        'membership_starts_at',
        'membership_ends_at',
        'is_active',
        'comment',
        'confirmation_mail_sent_at',
        'expiry_warning_14d_mail_sent_at',
        'expiry_warning_7d_mail_sent_at',
        'expired_mail_sent_at',
    ];

    protected $with = ['account'];

    protected function casts(): array
    {
        return [
            'membership_starts_at'            => 'date',
            'membership_ends_at'              => 'date',
            'is_active'                       => 'boolean',
            'confirmation_mail_sent_at'       => 'datetime',
            'expiry_warning_14d_mail_sent_at' => 'datetime',
            'expiry_warning_7d_mail_sent_at'  => 'datetime',
            'expired_mail_sent_at'            => 'datetime',
        ];
    }

    // ------------------------------------------------------------------
    // Relaties
    // ------------------------------------------------------------------

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function physicalCards(): HasMany
    {
        return $this->hasMany(PhysicalCard::class, 'holder_id')
            ->where('holder_type', PhysicalCard::TYPE_MEMBER)
            ->where('card_type', PhysicalCard::TYPE_MEMBER);
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getFirstNameAttribute(): ?string
    {
        return $this->account?->first_name;
    }

    public function getLastNameAttribute(): ?string
    {
        return $this->account?->last_name;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->account?->email;
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->account?->first_name ?? '') . ' ' . ($this->account?->last_name ?? ''));
    }
}
