<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('source', 50)->default('manual');
            $table->string('source_reference', 100)->nullable();

            $table->index(['source', 'source_reference'], 'order_items_source_reference_index');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_source_reference_index');
            $table->dropColumn(['source', 'source_reference']);
        });
    }
};
