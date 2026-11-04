<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Slimme stekker op een DIRIGERA-hub, gekoppeld aan een speelplek en een opstartgolf. */
class PowerPlug extends Model
{
    protected $fillable = [
        'tenant_id', 'power_hub_id', 'external_id', 'name', 'room', 'model',
        'setup_id', 'power_wave_id', 'wave_order',
        'is_on', 'is_reachable', 'power_w', 'has_metering', 'missing_since', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'setup_id'      => 'integer',
            'power_wave_id' => 'integer',
            'wave_order'    => 'integer',
            'is_on'         => 'boolean',
            'is_reachable'  => 'boolean',
            'power_w'       => 'float',
            'has_metering'  => 'boolean',
            'missing_since' => 'datetime',
            'synced_at'     => 'datetime',
        ];
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(PowerHub::class, 'power_hub_id');
    }

    public function setup(): BelongsTo
    {
        return $this->belongsTo(Setup::class);
    }

    public function wave(): BelongsTo
    {
        return $this->belongsTo(PowerWave::class, 'power_wave_id');
    }

    public function toPublicArray(): array
    {
        return [
            'id'            => $this->id,
            'hub_id'        => $this->power_hub_id,
            'external_id'   => $this->external_id,
            'name'          => $this->name,
            'room'          => $this->room,
            'model'         => $this->model,
            'setup_id'      => $this->setup_id,
            'wave_id'       => $this->power_wave_id,
            'wave_order'    => (int) $this->wave_order,
            'is_on'         => $this->is_on,
            'is_reachable'  => $this->is_reachable,
            'power_w'       => $this->power_w,
            'has_metering'  => (bool) $this->has_metering,
            'missing'       => $this->missing_since !== null,
            'synced_at'     => $this->synced_at?->toIso8601String(),
        ];
    }
}
