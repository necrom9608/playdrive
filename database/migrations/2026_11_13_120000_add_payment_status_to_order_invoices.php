<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Betaalstatus van facturen (bv. bedrijven die achteraf betalen: betaalwijze "Op factuur").
 * WeFact is de bron: Playdrive haalt de status elk uur op (invoices:sync-wefact).
 *
 * - order_invoices.payment_status: open | overdue | partial | paid | credited | cancelled
 * - amount_outstanding, due_date, reminders_count, last_reminder_at, synced_at
 * - tenants.wefact_payment_term: betaaltermijn in dagen voor "Op factuur" (leeg = 14)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('order_invoices', 'payment_status')) {
                $table->string('payment_status', 20)->nullable()->index()->after('paid_marked_at');
            }
            if (! Schema::hasColumn('order_invoices', 'amount_outstanding')) {
                $table->decimal('amount_outstanding', 10, 2)->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('order_invoices', 'due_date')) {
                $table->date('due_date')->nullable()->after('amount_outstanding');
            }
            if (! Schema::hasColumn('order_invoices', 'reminders_count')) {
                $table->unsignedSmallInteger('reminders_count')->default(0)->after('due_date');
            }
            if (! Schema::hasColumn('order_invoices', 'last_reminder_at')) {
                $table->timestamp('last_reminder_at')->nullable()->after('reminders_count');
            }
            if (! Schema::hasColumn('order_invoices', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('last_reminder_at');
            }
        });

        if (! Schema::hasColumn('tenants', 'wefact_payment_term')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->unsignedSmallInteger('wefact_payment_term')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('order_invoices', function (Blueprint $table) {
            foreach (['payment_status', 'amount_outstanding', 'due_date', 'reminders_count', 'last_reminder_at', 'synced_at'] as $column) {
                if (Schema::hasColumn('order_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasColumn('tenants', 'wefact_payment_term')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('wefact_payment_term');
            });
        }
    }
};
