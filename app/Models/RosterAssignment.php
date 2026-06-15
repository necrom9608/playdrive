<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persoon-in-blok. De rol volgt uit de shift (eigenschap van het blok).
 * source: 'template' (automatisch uit een standaard-invuller) | 'manual'.
 */
class RosterAssignment extends Model
{
    public const SOURCE_TEMPLATE = 'template';
    public const SOURCE_MANUAL   = 'manual';

    protected $fillable = [
        'tenant_id', 'shift_id', 'user_id', 'source',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(RosterShift::class, 'shift_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
