<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosDevice extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'device_uuid',
        'device_token',
        'display_device_id',
        'last_seen_at',
        'is_active',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function displayDevice(): BelongsTo
    {
        return $this->belongsTo(DisplayDevice::class);
    }
}
