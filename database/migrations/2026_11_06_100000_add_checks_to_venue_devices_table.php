<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controles die een Playscope-toestel meestuurt met de heartbeat (venue_devices.checks, JSON).
 *
 * - input_group: true = de gebruiker zit in de groep "input" (toetsenbord/muis leesbaar, nodig om
 *                games af te sluiten bij inactiviteit), false = ontbreekt, null = niet van toepassing
 *                (Windows).
 *
 * Getoond als waarschuwing bij het toestel (Speelplekken → Toestellen en Bediening).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('venue_devices', 'checks')) {
            Schema::table('venue_devices', function (Blueprint $table) {
                $table->json('checks')->nullable()->after('state_updated_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('venue_devices', 'checks')) {
            Schema::table('venue_devices', function (Blueprint $table) {
                $table->dropColumn('checks');
            });
        }
    }
};
