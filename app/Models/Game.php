<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Game in de catalogus van een venue. Playscope meldt wat er geïnstalleerd is;
 * de indeling (categorieën, spelers, verborgen/uitgelicht) gebeurt in de backoffice.
 */
class Game extends Model
{
    public const SOURCES = [
        'steam'       => 'Steam',
        'teknoparrot' => 'TeknoParrot',
    ];

    /** Keuzes voor "spelers op één scherm" in de backoffice. */
    public const LOCAL_PLAYER_OPTIONS = [1, 2, 4, 8];

    protected $fillable = [
        'tenant_id', 'source', 'external_id', 'name',
        'local_players_max', 'lan_multiplayer', 'is_hidden', 'is_featured',
        'first_seen_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'local_players_max' => 'integer',
            'lan_multiplayer'   => 'boolean',
            'is_hidden'         => 'boolean',
            'is_featured'       => 'boolean',
            'first_seen_at'     => 'datetime',
            'reviewed_at'       => 'datetime',
            'metadata'            => 'array',
            'metadata_fetched_at' => 'datetime',
            'auto_fields'         => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(GameCategory::class, 'game_game_category');
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(VenueDevice::class, 'venue_device_games')->withPivot('installed_at');
    }

    public function isNew(): bool
    {
        return $this->reviewed_at === null;
    }

    /** Cover voor de backoffice (Steam-CDN); andere bronnen hebben (nog) geen cover. */
    public function coverUrl(): ?string
    {
        return $this->source === 'steam' && ctype_digit($this->external_id)
            ? "https://cdn.cloudflare.steamstatic.com/steam/apps/{$this->external_id}/header.jpg"
            : null;
    }
}
