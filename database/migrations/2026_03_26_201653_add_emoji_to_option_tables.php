<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_types', function (Blueprint $table) {
            $table->string('emoji', 10)->nullable()->after('code');
        });

        Schema::table('stay_options', function (Blueprint $table) {
            $table->string('emoji', 10)->nullable()->after('code');
        });

        Schema::table('catering_options', function (Blueprint $table) {
            $table->string('emoji', 10)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('event_types', function (Blueprint $table) {
            $table->dropColumn('emoji');
        });

        Schema::table('stay_options', function (Blueprint $table) {
            $table->dropColumn('emoji');
        });

        Schema::table('catering_options', function (Blueprint $table) {
            $table->dropColumn('emoji');
        });
    }
};
