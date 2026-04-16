<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_category_id',
        'name',
        'slug',
        'description',
        'image_path',
        'price_excl_vat',
        'price_incl_vat',
        'vat_rate',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_excl_vat' => 'decimal:2',
        'price_incl_vat' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    public function cateringOptionLinks(): HasMany
    {
        return $this->hasMany(CateringOptionProduct::class);
    }
}
