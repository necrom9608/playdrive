<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('physical_cards') || ! Schema::hasColumn('physical_cards', 'voucher_template_id')) {
            return;
        }

        DB::statement('ALTER TABLE `physical_cards` MODIFY `voucher_template_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('physical_cards') || ! Schema::hasColumn('physical_cards', 'voucher_template_id')) {
            return;
        }

        DB::statement('ALTER TABLE `physical_cards` MODIFY `voucher_template_id` BIGINT UNSIGNED NOT NULL');
    }
};
