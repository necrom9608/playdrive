<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deelnemer(s) die vroeger stoppen met spelen. Blijven op de groepsrekening,
 * maar betalen speeltijd tot stopped_at.
 */
class RegistrationEarlyStop extends Model
{
    protected $fillable = [
        'tenant_id',
        'registration_id',
        'participants_children',
        'participants_adults',
        'registration_subscriber_id',
        'stopped_at',
        'created_by',
    ];

    protected $casts = [
        'participants_children' => 'integer',
        'participants_adults'   => 'integer',
        'stopped_at'            => 'datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(RegistrationSubscriber::class, 'registration_subscriber_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalAttribute(): int
    {
        return (int) $this->participants_children + (int) $this->participants_adults;
    }
}
