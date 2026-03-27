<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('paid');
            $table->string('source')->default('walk_in');
            $table->decimal('subtotal_excl_vat', 10, 2)->default(0);
            $table->decimal('total_vat', 10, 2)->default(0);
            $table->decimal('total_incl_vat', 10, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'registration_id']);
            $table->index(['tenant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
