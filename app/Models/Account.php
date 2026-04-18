<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Account is de globale identiteit van een PlayDrive-gebruiker.
 * Eén account kan gekoppeld zijn aan meerdere tenants via TenantMembership.
 *
 * Uitgebreid met HasApiTokens voor de member-api Sanctum token auth.
 */
class Account extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'phone',
        'birth_date',
        'street',
        'house_number',
        'box',
        'postal_code',
        'city',
        'country',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'password'   => 'hashed',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
