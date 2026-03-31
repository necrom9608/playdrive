<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('registrations')) {
            return;
        }

        Schema::table('registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('registrations', 'is_member')) {
                $table->boolean('is_member')->default(false)->after('outside_opening_hours');
            }

            if (! Schema::hasColumn('registrations', 'member_id')) {
                $table->foreignId('member_id')->nullable()->after('is_member')->constrained('members')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('registrations')) {
            return;
        }

        Schema::table('registrations', function (Blueprint $table) {
            if (Schema::hasColumn('registrations', 'member_id')) {
                $table->dropConstrainedForeignId('member_id');
            }

            if (Schema::hasColumn('registrations', 'is_member')) {
                $table->dropColumn('is_member');
            }
        });
    }
};
