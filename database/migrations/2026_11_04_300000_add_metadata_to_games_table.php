<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatisch opgehaalde gegevens per game (Steam, SteamSpy, PCGamingWiki), zie Domain\Games\GameMetadata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            if (! Schema::hasColumn('games', 'metadata')) {
                $table->json('metadata')->nullable()->after('lan_multiplayer');
            }
            if (! Schema::hasColumn('games', 'metadata_status')) {
                $table->string('metadata_status', 20)->nullable()->after('metadata');   // ok | not_found | error | skipped
            }
            if (! Schema::hasColumn('games', 'metadata_fetched_at')) {
                $table->timestamp('metadata_fetched_at')->nullable()->after('metadata_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['metadata', 'metadata_status', 'metadata_fetched_at']);
        });
    }
};
