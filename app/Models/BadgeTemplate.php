<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'template_type',
        'description',
        'is_default',
        'config_json',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'config_json' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
