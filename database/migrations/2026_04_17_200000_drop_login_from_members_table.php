<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('members', 'login')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropColumn('login');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('members', 'login')) {
            Schema::table('members', function (Blueprint $table) {
                $table->string('login')->nullable()->after('email');
            });
        }
    }
};
