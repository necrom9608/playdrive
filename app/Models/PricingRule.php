<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    public const TYPE_DURATION = 'duration';
    public const TYPE_CATERING = 'catering';

    protected $fillable = [
        'tenant_id',
        'pricing_profile_id',
        'type',
        'name',
        'description',
        'conditions',
        'actions',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function pricingProfile(): BelongsTo
    {
        return $this->belongsTo(PricingProfile::class);
    }
}
