<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArcadeCard extends Model
{
    protected $fillable = ['tenant_id', 'arcade_player_id', 'cid', 'print'];

    public function player(): BelongsTo
    {
        return $this->belongsTo(ArcadePlayer::class, 'arcade_player_id');
    }
}
