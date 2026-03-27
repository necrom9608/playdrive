<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('username')->nullable()->after('name')->unique();
            $table->string('street')->nullable()->after('password');
            $table->string('house_number', 50)->nullable()->after('street');
            $table->string('bus', 50)->nullable()->after('house_number');
            $table->string('postal_code', 20)->nullable()->after('bus');
            $table->string('city')->nullable()->after('postal_code');
            $table->boolean('is_active')->default(true)->after('city');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username',
                'street',
                'house_number',
                'bus',
                'postal_code',
                'city',
                'is_active',
                'sort_order',
            ]);
        });
    }
};
