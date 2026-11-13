<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Factuur via WeFact: bestelbonnummer van de klant (→ referentie op de factuur, ook in Peppol)
 * en extra commentaar (→ notitieregel op de factuur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('order_invoices', 'po_number')) {
                $table->string('po_number', 100)->nullable()->after('country');
            }
            if (! Schema::hasColumn('order_invoices', 'comment')) {
                $table->text('comment')->nullable()->after('po_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_invoices', function (Blueprint $table) {
            foreach (['po_number', 'comment'] as $column) {
                if (Schema::hasColumn('order_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
