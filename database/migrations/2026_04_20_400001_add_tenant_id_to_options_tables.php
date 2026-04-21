<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * hotfix v2
 * ─────────────────────────────────────────────────────────────────────────────
 * Voegt tenant_id toe aan event_types, stay_options en catering_options
 * zodat deze per tenant beheerd kunnen worden.
 *
 * Bestaande rijen worden gekoppeld aan tenant 1 (Game-INN) als default.
 * ─────────────────────────────────────────────────────────────────────────────
 */
return new class extends Migration
{
    public function up(): void
    {
        // event_types
        if (Schema::hasTable('event_types') && ! Schema::hasColumn('event_types', 'tenant_id')) {
            Schema::table('event_types', function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->onDelete('cascade');
            });

            // Koppel bestaande rijen aan tenant 1
            DB::table('event_types')->whereNull('tenant_id')->update(['tenant_id' => 1]);

            Schema::table('event_types', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable(false)->change();
            });
        }

        // stay_options
        if (Schema::hasTable('stay_options') && ! Schema::hasColumn('stay_options', 'tenant_id')) {
            Schema::table('stay_options', function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->onDelete('cascade');
            });

            DB::table('stay_options')->whereNull('tenant_id')->update(['tenant_id' => 1]);

            Schema::table('stay_options', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable(false)->change();
            });
        }

        // catering_options
        if (Schema::hasTable('catering_options') && ! Schema::hasColumn('catering_options', 'tenant_id')) {
            Schema::table('catering_options', function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->onDelete('cascade');
            });

            DB::table('catering_options')->whereNull('tenant_id')->update(['tenant_id' => 1]);

            Schema::table('catering_options', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['event_types', 'stay_options', 'catering_options'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['tenant_id']);
                    $t->dropColumn('tenant_id');
                });
            }
        }
    }
};
