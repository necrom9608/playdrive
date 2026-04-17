<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voegt account_id toe aan registrations en vult deze via de keten:
 * registrations.member_id → tenant_memberships.legacy_member_id → account_id
 *
 * member_id blijft bestaan voor backward compat en wordt in een latere
 * migratie verwijderd zodra alle code op account_id is overgezet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('member_id')->index();
        });

        // Vul account_id in voor alle bestaande registraties met een member_id,
        // maar NIET voor registraties die nog op checked_in staan — die worden
        // anders meteen uitgecheckt bij de eerste toggleAttendance.
        DB::statement("
            UPDATE registrations r
            INNER JOIN tenant_memberships tm ON tm.legacy_member_id = r.member_id
            SET r.account_id = tm.account_id
            WHERE r.member_id IS NOT NULL
              AND r.account_id IS NULL
              AND r.status != 'checked_in'
        ");

        // checked_in registraties apart: alleen als ze van vandaag zijn (actieve sessies)
        DB::statement("
            UPDATE registrations r
            INNER JOIN tenant_memberships tm ON tm.legacy_member_id = r.member_id
            SET r.account_id = tm.account_id
            WHERE r.member_id IS NOT NULL
              AND r.account_id IS NULL
              AND r.status = 'checked_in'
              AND DATE(r.checked_in_at) = CURDATE()
        ");
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('account_id');
        });
    }
};
