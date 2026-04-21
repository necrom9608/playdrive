<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_form_configs', function (Blueprint $table) {
            // Hoeveel uur voor het event annuleren nog toegelaten is (null = altijd)
            $table->unsignedSmallInteger('cancellation_hours_before')->default(24)->after('outside_hours_warning_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('booking_form_configs', function (Blueprint $table) {
            $table->dropColumn('cancellation_hours_before');
        });
    }
};
