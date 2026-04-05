<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_gift_voucher_id')->nullable()->constrained('gift_vouchers')->nullOnDelete();
            $table->foreignId('last_gift_voucher_id')->nullable()->constrained('gift_vouchers')->nullOnDelete();
            $table->string('label')->nullable();
            $table->string('internal_reference')->nullable();
            $table->string('rfid_uid');
            $table->string('status')->default('stock');
            $table->text('notes')->nullable();
            $table->dateTime('printed_at')->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'rfid_uid']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'voucher_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_cards');
    }
};
