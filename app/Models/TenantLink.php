<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantLink extends Model
{
    protected $fillable = [
        'tenant_id',
        'type',
        'url',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
