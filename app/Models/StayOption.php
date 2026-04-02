<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StayOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'emoji',
        'duration_minutes',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'duration_minutes' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
