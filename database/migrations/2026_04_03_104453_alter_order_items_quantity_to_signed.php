<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE order_items MODIFY quantity INT NOT NULL DEFAULT 1');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE order_items MODIFY quantity INT UNSIGNED NOT NULL DEFAULT 1');
    }
};
