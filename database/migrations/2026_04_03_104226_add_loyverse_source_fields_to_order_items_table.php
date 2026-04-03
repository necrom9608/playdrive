<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'source')) {
                $table->string('source', 32)->nullable();
            }

            if (! Schema::hasColumn('order_items', 'source_reference')) {
                $table->string('source_reference', 191)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'source_reference')) {
                $table->dropColumn('source_reference');
            }

            if (Schema::hasColumn('order_items', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
