<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Asphyxia-profiel (refid) bij een venue; account_id = gekoppeld Playdrive-account. */
class ArcadePlayer extends Model
{
    public const LINK_CARD   = 'card';
    public const LINK_MANUAL = 'manual';
    public const LINK_STAFF  = 'staff';
    /** Speler ontkoppelde zelf: niet opnieuw automatisch koppelen via de lidkaart. */
    public const LINK_UNLINKED = 'unlinked';

    protected $fillable = [
        'tenant_id', 'refid', 'name', 'games', 'pin_hash',
        'account_id', 'linked_via', 'linked_at', 'last_played_at',
    ];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return [
            'games'          => 'array',
            'linked_at'      => 'datetime',
            'last_played_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(ArcadeCard::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ArcadeScore::class);
    }

    public function plays(): HasMany
    {
        return $this->hasMany(ArcadePlay::class);
    }

    /** Naam zoals de speler hem in een game koos; anders de Asphyxia-naam. */
    public function displayName(?string $game = null): string
    {
        $games = $this->games ?? [];
        if ($game && ! empty($games[$game]['name'])) {
            return (string) $games[$game]['name'];
        }
        foreach ($games as $info) {
            if (! empty($info['name'])) {
                return (string) $info['name'];
            }
        }

        return (string) ($this->name ?: 'Speler');
    }
}
