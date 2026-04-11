<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('members') || Schema::hasColumn('members', 'birth_date')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('members') || ! Schema::hasColumn('members', 'birth_date')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('birth_date');
        });
    }
};
