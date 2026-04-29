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
        'tagline',
        'public_description',
        'slug',
        'public_slug',
        'public_status',
        'published_at',
        'subscription_tier',
        'is_active',
        'primary_domain',
        'street',
        'number',
        'postal_code',
        'city',
        'country',
        'latitude',
        'longitude',
        'target_audiences',
        'vat_number',
        'phone',
        'email',
        'website_url',
        'logo_path',
        'hero_image_path',
        'video_url',
        'receipt_footer',
    ];

    protected $appends = [
        'logo_url',
        'hero_image_url',
        'display_name',
        'full_address',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'published_at'      => 'datetime',
        'latitude'          => 'decimal:7',
        'longitude'         => 'decimal:7',
        'target_audiences'  => 'array',
    ];

    // ------------------------------------------------------------------
    // Relaties
    // ------------------------------------------------------------------

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

    public function photos(): HasMany
    {
        return $this->hasMany(TenantPhoto::class)->orderBy('sort_order');
    }

    public function links(): HasMany
    {
        return $this->hasMany(TenantLink::class)->orderBy('sort_order');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TenantActivity::class)->orderBy('sort_order');
    }

    public function amenities(): HasMany
    {
        return $this->hasMany(TenantAmenity::class);
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    public function getHeroImageUrlAttribute(): ?string
    {
        if (! $this->hero_image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->hero_image_path);
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

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopePubliclyVisible($query)
    {
        return $query->where('public_status', 'live')->whereNotNull('public_slug');
    }
}
