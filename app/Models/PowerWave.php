<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Opstartgolf: stekkers gaan per golf aan (en in omgekeerde volgorde uit). */
class PowerWave extends Model
{
    public const STAGGER_MAX = 30;
    public const DELAY_MAX = 600;

    protected $fillable = ['tenant_id', 'name', 'position', 'stagger_seconds', 'delay_after_seconds'];

    protected function casts(): array
    {
        return [
            'position'            => 'integer',
            'stagger_seconds'     => 'integer',
            'delay_after_seconds' => 'integer',
        ];
    }

    public function plugs(): HasMany
    {
        return $this->hasMany(PowerPlug::class);
    }

    public function toPublicArray(): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'position'            => (int) $this->position,
            'stagger_seconds'     => (int) $this->stagger_seconds,
            'delay_after_seconds' => (int) $this->delay_after_seconds,
        ];
    }
}
