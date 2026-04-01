<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisplayDevice extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'device_uuid',
        'device_token',
        'pairing_uuid',
        'last_seen_at',
        'current_mode',
        'current_payload',
        'is_active',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'current_payload' => 'array',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function posDevices(): HasMany
    {
        return $this->hasMany(PosDevice::class);
    }
}
