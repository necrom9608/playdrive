<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Groep Playscope-toestellen die samen hetzelfde spel draaien en samen wisselen
 * (bv. de racers). Logica: App\Domain\Setups\DeviceRotation.
 */
class DeviceGroup extends Model
{
    public const ROTATION_MINUTES_MIN = 5;
    public const ROTATION_MINUTES_MAX = 240;
    public const QUIET_SECONDS_MIN = 5;
    public const QUIET_SECONDS_MAX = 300;
    public const GRACE_MINUTES_MAX = 60;

    protected $fillable = [
        'tenant_id', 'name', 'rotation_minutes', 'quiet_seconds', 'grace_minutes',
        'is_paused', 'current_game_id', 'epoch', 'current_started_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rotation_minutes'   => 'integer',
            'quiet_seconds'      => 'integer',
            'grace_minutes'      => 'integer',
            'is_paused'          => 'boolean',
            'epoch'              => 'integer',
            'current_started_at' => 'datetime',
            'sort_order'         => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(VenueDevice::class);
    }

    /** Afspeellijst, in volgorde. */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'device_group_games')
            ->withPivot('sort_order')
            ->orderBy('device_group_games.sort_order');
    }

    public function currentGame(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'current_game_id');
    }

    /** @return int[] game-id's in volgorde */
    public function playlistIds(): array
    {
        return $this->games()->pluck('games.id')->map(fn ($id) => (int) $id)->all();
    }
}
