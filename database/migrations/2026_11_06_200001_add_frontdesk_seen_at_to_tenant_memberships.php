<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frontdesk → Leden → "Nieuwe leden": wanneer een medewerker een nieuw lid als "gezien" markeerde.
 * Daarna verdwijnt het uit het paneel (het lid zelf blijft gewoon bestaan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenant_memberships', 'frontdesk_seen_at')) {
            Schema::table('tenant_memberships', function (Blueprint $table) {
                $table->timestamp('frontdesk_seen_at')->nullable()->after('confirmation_mail_sent_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenant_memberships', 'frontdesk_seen_at')) {
            Schema::table('tenant_memberships', function (Blueprint $table) {
                $table->dropColumn('frontdesk_seen_at');
            });
        }
    }
};
