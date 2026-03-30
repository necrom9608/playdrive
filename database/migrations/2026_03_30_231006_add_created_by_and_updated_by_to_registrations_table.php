<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('registrations', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('outside_opening_hours')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('registrations', 'updated_by')) {
                $table->foreignId('updated_by')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            if (Schema::hasColumn('registrations', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }

            if (Schema::hasColumn('registrations', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
