<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CateringOptionProduct extends Model
{
    protected $fillable = [
        'tenant_id',
        'catering_option_id',
        'product_id',
        'applies_to_children',
        'applies_to_adults',
        'quantity_per_person',
        'sort_order',
    ];

    protected $casts = [
        'applies_to_children' => 'boolean',
        'applies_to_adults' => 'boolean',
        'quantity_per_person' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function cateringOption(): BelongsTo
    {
        return $this->belongsTo(CateringOption::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
