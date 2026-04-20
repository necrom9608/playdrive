<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningException extends Model
{
    protected $fillable = [
        'tenant_id',
        'date',
        'is_open',
        'open_from',
        'open_until',
        'label',
    ];

    protected $casts = [
        'date'    => 'date',
        'is_open' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
