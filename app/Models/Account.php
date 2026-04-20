<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Account is de globale identiteit van een PlayDrive-gebruiker.
 * Eén account kan gekoppeld zijn aan meerdere tenants via TenantMembership.
 *
 * Uitgebreid met HasApiTokens voor de member-api Sanctum token auth.
 * Uitgebreid met MustVerifyEmail voor e-mailbevestiging bij registratie.
 */
class Account extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasApiTokens, Notifiable;

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
            'birth_date'        => 'date',
            'password'          => 'hashed',
            'email_verified_at' => 'datetime',
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
