<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status "ingevoerd bij het sociaal secretariaat" per medewerker per maand.
 * snapshot_hash = hash van de dagwaarden op het moment van afvinken; wijkt de
 * huidige hash af, dan is er na invoer nog iets veranderd.
 */
class StaffPayrollMonth extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'period', 'submitted_at', 'submitted_by',
        'worked_minutes', 'snapshot_hash',
    ];

    protected $casts = [
        'submitted_at'   => 'datetime',
        'worked_minutes' => 'integer',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
