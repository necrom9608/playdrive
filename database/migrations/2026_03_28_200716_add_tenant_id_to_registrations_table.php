<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['tenant_id', 'event_date']);
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
