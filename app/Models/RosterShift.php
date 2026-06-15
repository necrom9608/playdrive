<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een uitgerold blok op een kalenderdag. Velden van het slot worden bij
 * genereren gesnapshot (role_id, tijden, desired_count, comment) zodat een al
 * geplande week niet verschuift als het sjabloon later wijzigt.
 *
 * source: 'template' (uit een slot gegenereerd) | 'manual' (los toegevoegd)
 */
class RosterShift extends Model
{
    public const SOURCE_TEMPLATE = 'template';
    public const SOURCE_MANUAL   = 'manual';

    protected $fillable = [
        'tenant_id', 'date', 'season_key', 'slot_id', 'role_id',
        'starts_at', 'ends_at', 'desired_count',
        'comment', 'note', 'status', 'source', 'sort_order',
    ];

    protected $casts = [
        'date'          => 'date',
        'desired_count' => 'integer',
        'sort_order'    => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(RosterRole::class, 'role_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RosterAssignment::class, 'shift_id');
    }
}
