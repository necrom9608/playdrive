<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Data-migratie: bestaande members → accounts + tenant_memberships.
 *
 * Logica:
 * - Per uniek e-mailadres wordt één account aangemaakt.
 * - Als meerdere members hetzelfde e-mailadres hebben (verschillende tenants),
 *   worden ze allemaal aan hetzelfde account gekoppeld.
 * - Members zonder e-mailadres krijgen elk hun eigen account op basis van ID.
 * - Na migratie wordt een legacy_member_id kolom bewaard op tenant_memberships
 *   zodat foreign keys (registrations.member_id, physical_cards.holder_id)
 *   gradueel omgezet kunnen worden.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('members')) {
            return;
        }

        // Voeg tijdelijk legacy_member_id toe zodat we de koppeling kunnen bewaren
        if (! Schema::hasColumn('tenant_memberships', 'legacy_member_id')) {
            Schema::table('tenant_memberships', function ($table) {
                $table->unsignedBigInteger('legacy_member_id')->nullable()->index()->after('id');
            });
        }

        $hasBirthDate   = Schema::hasColumn('members', 'birth_date');
        $hasPhone       = Schema::hasColumn('members', 'phone');
        $hasMemberType  = Schema::hasColumn('members', 'membership_type');
        $hasIsActive    = Schema::hasColumn('members', 'is_active');

        $members = DB::table('members')->orderBy('id')->get();

        // Bijhouden: email → account_id  (zodat dedup werkt)
        $emailToAccountId = [];

        foreach ($members as $member) {
            $email = ! empty($member->email) ? strtolower(trim($member->email)) : null;

            // --- Account aanmaken of hergebruiken ---
            if ($email && isset($emailToAccountId[$email])) {
                $accountId = $emailToAccountId[$email];
            } else {
                $accountId = DB::table('accounts')->insertGetId([
                    'email'        => $email ?? sprintf('member-%d@migrated.local', $member->id),
                    'first_name'   => $member->first_name,
                    'last_name'    => $member->last_name,
                    'phone'        => $hasPhone ? ($member->phone ?? null) : null,
                    'birth_date'   => $hasBirthDate ? ($member->birth_date ?? null) : null,
                    'street'       => $member->street ?? null,
                    'house_number' => $member->house_number ?? null,
                    'box'          => $member->box ?? null,
                    'postal_code'  => $member->postal_code ?? null,
                    'city'         => $member->city ?? null,
                    'country'      => $member->country ?? null,
                    'password'     => ! empty($member->password) ? $member->password : null,
                    'created_at'   => $member->created_at,
                    'updated_at'   => $member->updated_at,
                ]);

                if ($email) {
                    $emailToAccountId[$email] = $accountId;
                }
            }

            // --- TenantMembership aanmaken ---
            // rfid_uid: check op conflicten binnen dezelfde tenant (edge case bij dubbele migratie)
            $rfidUid = ! empty($member->rfid_uid) ? $member->rfid_uid : null;
            if ($rfidUid) {
                $conflict = DB::table('tenant_memberships')
                    ->where('tenant_id', $member->tenant_id)
                    ->where('rfid_uid', $rfidUid)
                    ->exists();
                if ($conflict) {
                    $rfidUid = null;
                }
            }

            DB::table('tenant_memberships')->insert([
                'legacy_member_id'           => $member->id,
                'account_id'                 => $accountId,
                'tenant_id'                  => $member->tenant_id,
                'membership_type'            => ($hasMemberType && ! empty($member->membership_type)) ? $member->membership_type : 'adult',
                'rfid_uid'                   => $rfidUid,
                'membership_starts_at'       => $member->membership_starts_at ?? null,
                'membership_ends_at'         => $member->membership_ends_at ?? null,
                'is_active'                  => $hasIsActive ? (bool) $member->is_active : true,
                'comment'                    => $member->comment ?? null,
                'confirmation_mail_sent_at'  => $member->confirmation_mail_sent_at ?? null,
                'expiry_warning_mail_sent_at' => $member->expiry_warning_mail_sent_at ?? null,
                'expired_mail_sent_at'       => $member->expired_mail_sent_at ?? null,
                'created_at'                 => $member->created_at,
                'updated_at'                 => $member->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        // Verwijder gemigreerde data — pas op: dit is destructief
        DB::table('tenant_memberships')->truncate();
        DB::table('accounts')->truncate();

        if (Schema::hasColumn('tenant_memberships', 'legacy_member_id')) {
            Schema::table('tenant_memberships', function ($table) {
                $table->dropColumn('legacy_member_id');
            });
        }
    }
};
