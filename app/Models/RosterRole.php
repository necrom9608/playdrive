<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Beheerde rol per tenant (bar, onthaal, VR-zone, ...).
 * Inactief i.p.v. verwijderen zodat historische shiften hun rol behouden.
 */
class RosterRole extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'color', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
