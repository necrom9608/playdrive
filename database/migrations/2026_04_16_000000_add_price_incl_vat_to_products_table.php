<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price_incl_vat', 10, 2)->nullable()->after('price_excl_vat');
        });

        // Backfill existing rows: price_incl_vat = round(price_excl_vat * (1 + vat_rate / 100), 2)
        DB::statement('
            UPDATE products
            SET price_incl_vat = ROUND(price_excl_vat * (1 + vat_rate / 100), 2)
            WHERE price_incl_vat IS NULL
        ');

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price_incl_vat', 10, 2)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price_incl_vat');
        });
    }
};
