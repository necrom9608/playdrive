<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'membership_type',
        'login',
        'email',
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
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'membership_starts_at' => 'date',
            'membership_ends_at' => 'date',
            'confirmation_mail_sent_at' => 'datetime',
            'expiry_warning_mail_sent_at' => 'datetime',
            'expired_mail_sent_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
