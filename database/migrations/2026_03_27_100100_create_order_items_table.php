<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price_excl_vat', 10, 2)->default(0);
            $table->decimal('unit_price_incl_vat', 10, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('line_subtotal_excl_vat', 10, 2)->default(0);
            $table->decimal('line_vat', 10, 2)->default(0);
            $table->decimal('line_total_incl_vat', 10, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
