<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén blok van het algemene weekrooster van een medewerker.
 * (tenant_id, user_id, weekday, block_index) is uniek.
 */
class RosterTemplate extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id',
        'weekday', 'block_index',
        'starts_at', 'ends_at', 'label',
    ];

    protected $casts = [
        'weekday'     => 'integer',
        'block_index' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
