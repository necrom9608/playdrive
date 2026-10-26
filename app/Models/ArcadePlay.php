<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén regel speelhistoriek. Bij DRS en DDR is dit een echte play met de score van die play;
 * bij pop'n/SDVX/NOSTALGIA bewaart Asphyxia enkel het beste resultaat, dus is score enkel
 * ingevuld als de play een nieuw record opleverde (anders: "gespeeld").
 */
class ArcadePlay extends Model
{
    protected $fillable = [
        'tenant_id', 'arcade_player_id', 'source_key', 'game', 'song_id', 'chart', 'chart_order',
        'score', 'grade', 'grade_label', 'clear', 'clear_label', 'combo', 'plays',
        'is_new_best', 'extra', 'played_at',
    ];

    protected function casts(): array
    {
        return [
            'extra'       => 'array',
            'is_new_best' => 'boolean',
            'played_at'   => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(ArcadePlayer::class, 'arcade_player_id');
    }
}
