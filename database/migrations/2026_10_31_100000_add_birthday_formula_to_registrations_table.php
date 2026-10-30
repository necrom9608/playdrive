<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verjaardag met (true) of zonder (false) verjaardagsformule. Leeg = niet
 * van toepassing (andere soorten, of een formulier dat het niet vraagt).
 * De website van de zaak stuurt het mee als birthday_formula; mails en
 * overzichten tonen het bij de soort ("Verjaardag — met verjaardagsformule").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('registrations', 'birthday_formula')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->boolean('birthday_formula')->nullable()->after('event_type_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('registrations', 'birthday_formula')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->dropColumn('birthday_formula');
            });
        }
    }
};
