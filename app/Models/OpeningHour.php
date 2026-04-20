<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningHour extends Model
{
    protected $fillable = [
        'tenant_id',
        'season_key',
        'weekday',
        'is_open',
        'open_from',
        'open_until',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'weekday' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
