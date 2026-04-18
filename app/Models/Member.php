<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Member is een backward-compatibele view op de gecombineerde
 * accounts + tenant_memberships structuur.
 *
 * Het model leest en schrijft nog steeds naar de `members` tabel
 * maar delegates geleidelijk naar Account / TenantMembership.
 *
 * Het `login` veld wordt niet meer gebruikt: e-mail is de identifier.
 */
class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'membership_type',
        'email',
        'phone',
        'password',
        'street',
        'house_number',
        'box',
        'postal_code',
        'city',
        'country',
        'rfid_uid',
        'birth_date',
        'comment',
        'membership_starts_at',
        'membership_ends_at',
        'confirmation_mail_sent_at',
        'expiry_warning_mail_sent_at',
        'expired_mail_sent_at',
        'is_active',
        // legacy_member_id wordt niet via fillable gezet, alleen intern
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'birth_date'                  => 'date',
            'membership_starts_at'        => 'date',
            'membership_ends_at'          => 'date',
            'confirmation_mail_sent_at'   => 'datetime',
            'expiry_warning_mail_sent_at' => 'datetime',
            'expired_mail_sent_at'        => 'datetime',
            'password'                    => 'hashed',
            'is_active'                   => 'boolean',
        ];
    }

    // ------------------------------------------------------------------
    // Relaties
    // ------------------------------------------------------------------

    public function physicalCards(): HasMany
    {
        return $this->hasMany(PhysicalCard::class, 'holder_id')
            ->where('holder_type', PhysicalCard::TYPE_MEMBER)
            ->where('card_type', PhysicalCard::TYPE_MEMBER);
    }

    /**
     * Het bijbehorende Account (indien al gemigreerd).
     * Via legacy_member_id op tenant_memberships → account.
     */
    public function tenantMembership(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TenantMembership::class, 'legacy_member_id');
    }

    // ------------------------------------------------------------------
    // Accessor: login veld wordt niet meer gebruikt, email is de login
    // ------------------------------------------------------------------

    public function getLoginAttribute(): ?string
    {
        return $this->email;
    }
}
