<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantActivity extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'icon_key',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_visible' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }
}
