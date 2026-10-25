<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnees binnen een registratie.
 *
 * Een registratie (groep) kan één of meer abonnees bevatten. Abonnees betalen
 * geen speeltijd; catering wordt wel aangerekend. participant_scope bepaalt in
 * welke deelnemerscategorie de abonnee meetelt (student → children,
 * adult → adults).
 *
 * Bestaande lidbezoeken (is_member + account_id) worden mee overgezet zodat
 * alle abonnee-bezoeken via deze tabel lopen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('registration_subscribers')) {
            Schema::create('registration_subscribers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
                $table->foreignId('tenant_membership_id')->nullable()->constrained('tenant_memberships')->nullOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
                $table->string('participant_scope', 20)->default('adults');
                $table->timestamps();

                $table->unique(['registration_id', 'tenant_membership_id'], 'reg_subscribers_reg_membership_unique');
                $table->index(['tenant_id', 'account_id']);
            });
        }

        // Backfill: bestaande lidbezoeken
        DB::statement("
            INSERT INTO registration_subscribers
                (tenant_id, registration_id, tenant_membership_id, account_id, participant_scope, created_at, updated_at)
            SELECT
                r.tenant_id,
                r.id,
                (SELECT MIN(tm.id) FROM tenant_memberships tm
                  WHERE tm.tenant_id = r.tenant_id AND tm.account_id = r.account_id),
                r.account_id,
                CASE WHEN COALESCE(r.participants_children, 0) > 0 AND COALESCE(r.participants_adults, 0) = 0
                     THEN 'children' ELSE 'adults' END,
                NOW(),
                NOW()
            FROM registrations r
            WHERE r.is_member = 1
              AND r.account_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM registration_subscribers rs WHERE rs.registration_id = r.id
              )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_subscribers');
    }
};
