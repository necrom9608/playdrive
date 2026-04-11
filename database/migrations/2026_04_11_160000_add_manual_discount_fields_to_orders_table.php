<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'manual_discount_amount')) {
                $table->decimal('manual_discount_amount', 10, 2)->default(0)->after('total_incl_vat');
            }

            if (! Schema::hasColumn('orders', 'manual_discount_label')) {
                $table->string('manual_discount_label', 120)->nullable()->after('manual_discount_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'manual_discount_label')) {
                $table->dropColumn('manual_discount_label');
            }

            if (Schema::hasColumn('orders', 'manual_discount_amount')) {
                $table->dropColumn('manual_discount_amount');
            }
        });
    }
};
