<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionSeason extends Model
{
    protected $fillable = [
        'region_code',
        'season_key',
        'season_name',
        'date_from',
        'date_until',
        'priority',
    ];

    protected $casts = [
        'date_from'  => 'date',
        'date_until' => 'date',
        'priority'   => 'integer',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }
}
