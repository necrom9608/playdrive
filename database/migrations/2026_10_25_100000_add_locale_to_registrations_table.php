<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taal van de klant (nl, fr, en). De website van de zaak stuurt ze mee;
 * de mails naar de klant gaan in die taal, de melding naar de zaak blijft
 * Nederlands.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('registrations', 'locale')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->string('locale', 5)->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('registrations', 'locale')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
