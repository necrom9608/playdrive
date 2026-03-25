<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'primary_domain',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }
}
