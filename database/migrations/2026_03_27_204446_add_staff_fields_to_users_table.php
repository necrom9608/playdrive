<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'street')) {
                $table->string('street')->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'house_number')) {
                $table->string('house_number', 50)->nullable()->after('street');
            }

            if (! Schema::hasColumn('users', 'bus')) {
                $table->string('bus', 50)->nullable()->after('house_number');
            }

            if (! Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('bus');
            }

            if (! Schema::hasColumn('users', 'city')) {
                $table->string('city')->nullable()->after('postal_code');
            }

            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('city');
            }

            if (! Schema::hasColumn('users', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'tenant_id',
                'username',
                'street',
                'house_number',
                'bus',
                'postal_code',
                'city',
                'is_active',
                'sort_order',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
