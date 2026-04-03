<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'name',
        'quantity',
        'unit_price_excl_vat',
        'unit_price_incl_vat',
        'vat_rate',
        'line_subtotal_excl_vat',
        'line_vat',
        'line_total_incl_vat',
        'sort_order',
        'source',
        'source_reference',
        'legacy_category',
        'legacy_article_number',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_excl_vat' => 'decimal:2',
        'unit_price_incl_vat' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'line_subtotal_excl_vat' => 'decimal:2',
        'line_vat' => 'decimal:2',
        'line_total_incl_vat' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

