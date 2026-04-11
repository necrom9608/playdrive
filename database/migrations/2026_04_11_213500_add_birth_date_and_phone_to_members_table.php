<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('password');
            }

            if (! Schema::hasColumn('members', 'phone')) {
                $table->string('phone', 100)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'birth_date')) {
                $table->dropColumn('birth_date');
            }

            if (Schema::hasColumn('members', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }
};
