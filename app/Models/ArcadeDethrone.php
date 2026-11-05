<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Iemand pakte de #1 op een chart af van een gekoppelde speler (zie ArcadeIngestService). */
class ArcadeDethrone extends Model
{
    protected $fillable = [
        'tenant_id', 'game', 'song_id', 'chart',
        'lost_account_id', 'lost_player_id', 'lost_score',
        'by_player_id', 'by_score',
        'notified_at', 'seen_at', 'reclaimed_at',
    ];

    protected function casts(): array
    {
        return [
            'song_id'      => 'integer',
            'lost_score'   => 'integer',
            'by_score'     => 'integer',
            'notified_at'  => 'datetime',
            'seen_at'      => 'datetime',
            'reclaimed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'lost_account_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function byPlayer(): BelongsTo
    {
        return $this->belongsTo(ArcadePlayer::class, 'by_player_id');
    }

    /** Nog niet teruggepakt en niet ouder dan 30 dagen. */
    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('reclaimed_at')->where('created_at', '>=', now()->subDays(30));
    }
}
