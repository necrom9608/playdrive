<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eén slot van het algemene rooster = de "vraag" voor een season_key x weekdag:
 * tijd, rol, gewenst aantal, staand commentaar. Standaard-invullers hangen er
 * via roster_slot_defaults onder.
 */
class RosterSlot extends Model
{
    protected $fillable = [
        'tenant_id', 'season_key', 'weekday', 'role_id',
        'starts_at', 'ends_at', 'desired_count', 'comment', 'sort_order',
    ];

    protected $casts = [
        'weekday'       => 'integer',
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

    public function defaults(): HasMany
    {
        return $this->hasMany(RosterSlotDefault::class, 'slot_id');
    }
}
