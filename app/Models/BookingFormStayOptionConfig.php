<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingFormStayOptionConfig extends Model
{
    protected $fillable = [
        'tenant_id',
        'stay_option_id',
        'show_in_form',
        'min_revenue_outside_hours_cents',
    ];

    protected $casts = [
        'show_in_form'                    => 'boolean',
        'min_revenue_outside_hours_cents' => 'integer',
    ];

    /**
     * Geeft het minimumbedrag terug als euro (float).
     * Retourneert null als er geen minimum ingesteld is.
     */
    public function getMinRevenueOutsideHoursEurosAttribute(): ?float
    {
        if ($this->min_revenue_outside_hours_cents === null) {
            return null;
        }

        return $this->min_revenue_outside_hours_cents / 100;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stayOption(): BelongsTo
    {
        return $this->belongsTo(StayOption::class);
    }
}
