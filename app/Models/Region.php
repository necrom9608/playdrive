<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = [
        'code',
        'name',
    ];

    public function seasons(): HasMany
    {
        return $this->hasMany(RegionSeason::class, 'region_code', 'code');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'region_code', 'code');
    }
}
