<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Beste resultaat van een speler op één chart (game + song + moeilijkheid). */
class ArcadeScore extends Model
{
    protected $fillable = [
        'tenant_id', 'arcade_player_id', 'game', 'song_id', 'chart', 'chart_order',
        'score', 'grade', 'grade_label', 'clear', 'clear_label', 'combo', 'play_count',
        'extra', 'achieved_at', 'last_played_at',
    ];

    protected function casts(): array
    {
        return [
            'extra'          => 'array',
            'score'          => 'integer',
            'clear'          => 'integer',
            'play_count'     => 'integer',
            'achieved_at'    => 'datetime',
            'last_played_at' => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(ArcadePlayer::class, 'arcade_player_id');
    }
}
