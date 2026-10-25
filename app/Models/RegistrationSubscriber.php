<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een abonnee die deel uitmaakt van een registratie (groep).
 * Abonnees betalen geen speeltijd; catering wel.
 */
class RegistrationSubscriber extends Model
{
    public const SCOPE_CHILDREN = 'children';
    public const SCOPE_ADULTS   = 'adults';

    protected $fillable = [
        'tenant_id',
        'registration_id',
        'tenant_membership_id',
        'account_id',
        'participant_scope',
    ];

    /**
     * Deelnemerscategorie op basis van het type abonnement.
     * Studenten vallen in de pricing onder kinderen/studenten.
     */
    public static function scopeForMembershipType(?string $membershipType): string
    {
        return $membershipType === 'student' ? self::SCOPE_CHILDREN : self::SCOPE_ADULTS;
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function tenantMembership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
