<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('registrations')) {
            Schema::table('registrations', function (Blueprint $table) {
                if (! Schema::hasColumn('registrations', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('registrations', 'updated_by')) {
                    $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('registrations', 'checked_in_by')) {
                    $table->foreignId('checked_in_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('registrations', 'checked_out_by')) {
                    $table->foreignId('checked_out_by')->nullable()->after('checked_out_at')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('registrations', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('checked_out_by');
                }

                if (! Schema::hasColumn('registrations', 'cancelled_by')) {
                    $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('registrations', 'no_show_at')) {
                    $table->timestamp('no_show_at')->nullable()->after('cancelled_by');
                }

                if (! Schema::hasColumn('registrations', 'no_show_by')) {
                    $table->foreignId('no_show_by')->nullable()->after('no_show_at')->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('orders', 'updated_by')) {
                    $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('orders', 'paid_by')) {
                    $table->foreignId('paid_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('orders', 'cancelled_by')) {
                    $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('orders', 'refunded_by')) {
                    $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('source_reference')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('order_items', 'updated_by')) {
                    $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                foreach (['updated_by', 'created_by'] as $column) {
                    if (Schema::hasColumn('order_items', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['refunded_by', 'cancelled_by', 'paid_by', 'updated_by', 'created_by'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
            });
        }

        if (Schema::hasTable('registrations')) {
            Schema::table('registrations', function (Blueprint $table) {
                foreach (['no_show_by', 'cancelled_by', 'checked_out_by', 'checked_in_by', 'updated_by', 'created_by'] as $column) {
                    if (Schema::hasColumn('registrations', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }

                foreach (['no_show_at', 'cancelled_at'] as $column) {
                    if (Schema::hasColumn('registrations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
