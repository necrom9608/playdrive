<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'product_id',
        'badge_template_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function badgeTemplate(): BelongsTo
    {
        return $this->belongsTo(BadgeTemplate::class, 'badge_template_id');
    }
}
