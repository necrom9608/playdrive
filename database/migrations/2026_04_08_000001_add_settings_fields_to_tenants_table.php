<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('name');
            $table->string('street')->nullable()->after('primary_domain');
            $table->string('number')->nullable()->after('street');
            $table->string('postal_code', 20)->nullable()->after('number');
            $table->string('city')->nullable()->after('postal_code');
            $table->string('country')->nullable()->after('city');
            $table->string('vat_number')->nullable()->after('country');
            $table->string('phone')->nullable()->after('vat_number');
            $table->string('email')->nullable()->after('phone');
            $table->string('logo_path')->nullable()->after('email');
            $table->text('receipt_footer')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'street',
                'number',
                'postal_code',
                'city',
                'country',
                'vat_number',
                'phone',
                'email',
                'logo_path',
                'receipt_footer',
            ]);
        });
    }
};
