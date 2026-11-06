<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klantenscherm: QR-code "Word gratis lid" (link naar /join/{venue}) aan of uit.
 * Standaard uit, zodat het scherm niets verandert tot de backoffice het aanzet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('display_settings') && ! Schema::hasColumn('display_settings', 'join_qr')) {
            Schema::table('display_settings', function (Blueprint $table) {
                $table->boolean('join_qr')->default(false)->after('crt');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('display_settings') && Schema::hasColumn('display_settings', 'join_qr')) {
            Schema::table('display_settings', function (Blueprint $table) {
                $table->dropColumn('join_qr');
            });
        }
    }
};
