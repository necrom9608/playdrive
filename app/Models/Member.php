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
        'username',
        'email',
        'password',
        'street',
        'house_number',
        'bus',
        'postal_code',
        'city',
        'rfid_uid',
        'comment',
        'membership_started_at',
        'membership_expires_at',
        'is_active',
        'confirmation_mail_sent_at',
        'expiry_warning_mail_sent_at',
        'expired_mail_sent_at',
        'sort_order',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'membership_started_at' => 'date',
            'membership_expires_at' => 'date',
            'is_active' => 'boolean',
            'confirmation_mail_sent_at' => 'datetime',
            'expiry_warning_mail_sent_at' => 'datetime',
            'expired_mail_sent_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
