<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bestelbonnummer en opmerking voor de factuur al bij de reservatie kunnen ingeven. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('registrations', 'invoice_po_number')) {
                $table->string('invoice_po_number', 100)->nullable()->after('invoice_city');
            }
            if (! Schema::hasColumn('registrations', 'invoice_comment')) {
                $table->text('invoice_comment')->nullable()->after('invoice_po_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            foreach (['invoice_comment', 'invoice_po_number'] as $column) {
                if (Schema::hasColumn('registrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
