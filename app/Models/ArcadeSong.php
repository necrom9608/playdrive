<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArcadeSong extends Model
{
    protected $fillable = ['game', 'song_id', 'title', 'artist', 'levels'];

    protected function casts(): array
    {
        return ['levels' => 'array'];
    }
}
