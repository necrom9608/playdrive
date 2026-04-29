<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAmenity extends Model
{
    protected $fillable = [
        'tenant_id',
        'key',
        'value',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }
}
