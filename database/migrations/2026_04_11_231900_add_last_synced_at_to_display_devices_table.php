<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('display_devices')) {
            return;
        }

        Schema::table('display_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('display_devices', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('last_seen_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('display_devices')) {
            return;
        }

        Schema::table('display_devices', function (Blueprint $table) {
            if (Schema::hasColumn('display_devices', 'last_synced_at')) {
                $table->dropColumn('last_synced_at');
            }
        });
    }
};
