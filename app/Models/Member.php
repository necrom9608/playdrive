<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'birth_date',
        'membership_type',
        'login',
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
        'comment',
        'membership_starts_at',
        'membership_ends_at',
        'confirmation_mail_sent_at',
        'expiry_warning_mail_sent_at',
        'expired_mail_sent_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'membership_starts_at' => 'date',
            'membership_ends_at' => 'date',
            'confirmation_mail_sent_at' => 'datetime',
            'expiry_warning_mail_sent_at' => 'datetime',
            'expired_mail_sent_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function physicalCards(): HasMany
    {
        return $this->hasMany(PhysicalCard::class, 'holder_id')
            ->where('holder_type', PhysicalCard::TYPE_MEMBER)
            ->where('card_type', PhysicalCard::TYPE_MEMBER);
    }
}
