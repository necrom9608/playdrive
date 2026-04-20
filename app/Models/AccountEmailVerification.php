<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountEmailVerification extends Model
{
    protected $fillable = [
        'account_id',
        'token',
        'tenant_slug',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
