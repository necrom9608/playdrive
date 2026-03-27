<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('invoice_requested')->default(false)->after('payment_method');
            $table->timestamp('invoice_exported_at')->nullable()->after('invoice_requested');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_requested',
                'invoice_exported_at',
            ]);
        });
    }
};
