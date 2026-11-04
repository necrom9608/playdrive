<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IKEA DIRIGERA-hub in een venue. Enkel bereikbaar in het lokale netwerk: de kassa (Tauri)
 * koppelt hem (knop op de hub) en stuurt hem aan; PlayDrive bewaart het token versleuteld.
 */
class PowerHub extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'host', 'token', 'paired_at', 'last_seen_at', 'last_error', 'sort_order',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token'        => 'encrypted',
            'paired_at'    => 'datetime',
            'last_seen_at' => 'datetime',
            'sort_order'   => 'integer',
        ];
    }

    public function plugs(): HasMany
    {
        return $this->hasMany(PowerPlug::class);
    }

    public function isPaired(): bool
    {
        return $this->paired_at !== null && filled($this->token);
    }

    public function toPublicArray(): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'host'         => $this->host,
            'paired'       => $this->isPaired(),
            'paired_at'    => $this->paired_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'last_error'   => $this->last_error,
        ];
    }
}
