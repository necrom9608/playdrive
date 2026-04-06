<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('physical_cards', function (Blueprint $table) {
            if (! Schema::hasColumn('physical_cards', 'render_image_path')) {
                $table->string('render_image_path')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('physical_cards', function (Blueprint $table) {
            if (Schema::hasColumn('physical_cards', 'render_image_path')) {
                $table->dropColumn('render_image_path');
            }
        });
    }
};
