<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Standaard-invuller van een slot: wordt bij genereren automatisch toegewezen
 * (per week nog aanpasbaar).
 */
class RosterSlotDefault extends Model
{
    protected $fillable = [
        'tenant_id', 'slot_id', 'user_id',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(RosterSlot::class, 'slot_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
