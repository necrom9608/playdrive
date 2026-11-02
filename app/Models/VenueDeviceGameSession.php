<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Speelbeurt op een Playscope-toestel (zie App\Domain\Setups\DeviceGameSessions). */
class VenueDeviceGameSession extends Model
{
    protected $fillable = [
        'uuid', 'tenant_id', 'venue_device_id', 'device_name', 'game_id', 'source', 'external_id',
        'game_name', 'started_at', 'running_at', 'ended_at', 'duration_seconds', 'active_seconds', 'end_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at'       => 'datetime',
            'running_at'       => 'datetime',
            'ended_at'         => 'datetime',
            'duration_seconds' => 'integer',
            'active_seconds'   => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(VenueDevice::class, 'venue_device_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
