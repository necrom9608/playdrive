<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');

            $table->timestamp('refunded_at')->nullable()->after('cancellation_reason');
            $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
            $table->decimal('refund_amount', 10, 2)->nullable()->after('refunded_by');
            $table->string('refund_method')->nullable()->after('refund_amount');
            $table->text('refund_reason')->nullable()->after('refund_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('refunded_by');

            $table->dropColumn([
                'cancelled_at',
                'cancellation_reason',
                'refunded_at',
                'refund_amount',
                'refund_method',
                'refund_reason',
            ]);
        });
    }
};
