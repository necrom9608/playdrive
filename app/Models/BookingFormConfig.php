<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingFormConfig extends Model
{
    protected $fillable = [
        'tenant_id',
        'is_active',
        'show_participant_children',
        'show_participant_adults',
        'show_participant_supervisors',
        'outside_hours_warning_enabled',
    ];

    protected $casts = [
        'is_active'                      => 'boolean',
        'show_participant_children'      => 'boolean',
        'show_participant_adults'        => 'boolean',
        'show_participant_supervisors'   => 'boolean',
        'outside_hours_warning_enabled'  => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
