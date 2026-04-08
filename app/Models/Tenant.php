<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'company_name',
        'slug',
        'is_active',
        'primary_domain',
        'street',
        'number',
        'postal_code',
        'city',
        'country',
        'vat_number',
        'phone',
        'email',
        'logo_path',
        'receipt_footer',
    ];

    protected $appends = [
        'logo_url',
        'display_name',
        'full_address',
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

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ?: $this->name;
    }

    public function getFullAddressAttribute(): ?string
    {
        $line1 = trim(implode(' ', array_filter([$this->street, $this->number])));
        $line2 = trim(implode(' ', array_filter([$this->postal_code, $this->city])));
        $parts = array_values(array_filter([$line1, $line2, $this->country]));

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }
}
