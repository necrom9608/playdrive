<?php

namespace App\Domain\Memberships;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Familieabonnement: één hoofdabonnement (membership_type = family) met
 * gezinsleden die elk een eigen TenantMembership hebben
 * (family_membership_id → hoofd).
 *
 * De geldigheid (start, einde, actief) van gezinsleden wordt altijd gelijk
 * gezet aan die van het hoofd. Zo werken check-in, abonnee-validatie en
 * statistieken zonder extra logica.
 */
class FamilyMembershipService
{
    /** Uitzondering wanneer een limiet van de zaak overschreden wordt. */
    public const ERROR_LIMIT = 'family_limit_reached';

    /**
     * Geldigheid van het hoofd doorzetten naar alle gezinsleden.
     */
    public function syncValidity(TenantMembership $head): void
    {
        if (! $head->isFamilyHead()) {
            return;
        }

        TenantMembership::query()
            ->where('family_membership_id', $head->id)
            ->update([
                'membership_starts_at' => $head->membership_starts_at?->toDateString(),
                'membership_ends_at'   => $head->membership_ends_at?->toDateString(),
                'is_active'            => (bool) $head->is_active,
                'updated_at'           => now(),
            ]);
    }

    /**
     * @return array{adults: ?int, children: ?int}
     */
    public function limits(int $tenantId): array
    {
        $tenant = Tenant::query()->find($tenantId);

        return [
            'adults'   => $tenant?->family_max_adults,
            'children' => $tenant?->family_max_children,
        ];
    }

    /**
     * Aantal personen in het gezin. Het hoofd telt mee als volwassene.
     *
     * @return array{adults: int, children: int}
     */
    public function counts(TenantMembership $head): array
    {
        $members = $head->familyMembers()->without('account')->get(['id', 'membership_type']);

        return [
            'adults'   => 1 + $members->where('membership_type', TenantMembership::TYPE_ADULT)->count(),
            'children' => $members->where('membership_type', TenantMembership::TYPE_STUDENT)->count(),
        ];
    }

    /**
     * Gezinslid toevoegen.
     *
     * $data: first_name, last_name, birth_date?, membership_type (adult|student),
     *        email?, rfid_uid?
     *
     * Met e-mail: bestaand account wordt hergebruikt. Zonder e-mail wordt een
     * beheerd account aangemaakt onder het account van het hoofd.
     *
     * @throws ValidationException
     */
    public function addMember(TenantMembership $head, array $data, bool $force = false): TenantMembership
    {
        if (! $head->isFamilyHead()) {
            throw ValidationException::withMessages(['family' => 'Dit is geen familieabonnement.']);
        }

        $type = $data['membership_type'] === TenantMembership::TYPE_STUDENT
            ? TenantMembership::TYPE_STUDENT
            : TenantMembership::TYPE_ADULT;

        // Limiet van de zaak
        if (! $force) {
            $limits = $this->limits((int) $head->tenant_id);
            $counts = $this->counts($head);
            $key    = $type === TenantMembership::TYPE_STUDENT ? 'children' : 'adults';

            if ($limits[$key] !== null && $counts[$key] >= $limits[$key]) {
                throw ValidationException::withMessages([
                    self::ERROR_LIMIT => sprintf(
                        'Limiet bereikt: max. %d %s per familieabonnement.',
                        $limits[$key],
                        $key === 'children' ? 'kinderen/studenten' : 'volwassenen'
                    ),
                ]);
            }
        }

        $rfid = trim((string) ($data['rfid_uid'] ?? '')) ?: null;

        if ($rfid && TenantMembership::query()
            ->where('tenant_id', $head->tenant_id)
            ->where('rfid_uid', $rfid)
            ->exists()) {
            throw ValidationException::withMessages(['rfid_uid' => 'Deze kaart is al gekoppeld aan een ander lid.']);
        }

        return DB::transaction(function () use ($head, $data, $type, $rfid) {
            $email = trim((string) ($data['email'] ?? '')) ?: null;
            $headAccount = $head->account;

            $account = $email ? Account::query()->where('email', $email)->first() : null;

            if (! $account) {
                $account = Account::query()->create([
                    'managed_by_account_id' => $email ? null : $headAccount?->id,
                    'email'        => $email ?: $this->placeholderEmail($head),
                    'first_name'   => $data['first_name'],
                    'last_name'    => $data['last_name'],
                    'birth_date'   => $data['birth_date'] ?? null,
                    // zelfde huishouden: adres van het hoofd overnemen
                    'street'       => $headAccount?->street,
                    'house_number' => $headAccount?->house_number,
                    'box'          => $headAccount?->box,
                    'postal_code'  => $headAccount?->postal_code,
                    'city'         => $headAccount?->city,
                    'country'      => $headAccount?->country,
                ]);
            }

            if ((int) $account->id === (int) $head->account_id) {
                throw ValidationException::withMessages(['email' => 'Dit is het account van het hoofd zelf.']);
            }

            $existing = TenantMembership::query()
                ->where('tenant_id', $head->tenant_id)
                ->where('account_id', $account->id)
                ->first();

            if ($existing) {
                if ($existing->isFamilyHead()) {
                    throw ValidationException::withMessages(['email' => "{$account->full_name} heeft zelf een familieabonnement."]);
                }

                if ($existing->family_membership_id && (int) $existing->family_membership_id !== (int) $head->id) {
                    throw ValidationException::withMessages(['email' => "{$account->full_name} zit al in een ander gezin."]);
                }
            }

            $payload = [
                'membership_type'      => $type,
                'family_membership_id' => $head->id,
                'membership_starts_at' => $head->membership_starts_at?->toDateString(),
                'membership_ends_at'   => $head->membership_ends_at?->toDateString(),
                'is_active'            => (bool) $head->is_active,
            ];

            if ($rfid) {
                $payload['rfid_uid'] = $rfid;
            }

            if ($existing) {
                $existing->update($payload);

                return $existing->fresh();
            }

            return TenantMembership::query()->create([
                ...$payload,
                'account_id' => $account->id,
                'tenant_id'  => $head->tenant_id,
            ]);
        });
    }

    /**
     * Gezinslid uit het gezin halen. Het lid blijft bestaan maar heeft
     * daarna geen abonnement meer.
     */
    public function detachMember(TenantMembership $head, TenantMembership $member): void
    {
        if ((int) $member->family_membership_id !== (int) $head->id) {
            throw ValidationException::withMessages(['member' => 'Dit lid hoort niet bij dit gezin.']);
        }

        $member->update([
            'family_membership_id' => null,
            'is_active'            => false,
            'membership_starts_at' => null,
            'membership_ends_at'   => null,
        ]);
    }

    public function familyName(TenantMembership $head): string
    {
        $lastName = trim((string) $head->account?->last_name);

        return $lastName !== '' ? "Familie {$lastName}" : "Gezin #{$head->id}";
    }

    private function placeholderEmail(TenantMembership $head): string
    {
        do {
            $email = sprintf('gezin-%d-%s@playdrive.local', $head->id, Str::lower(Str::random(8)));
        } while (Account::query()->where('email', $email)->exists());

        return $email;
    }
}
