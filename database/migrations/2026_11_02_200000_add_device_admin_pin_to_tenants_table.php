<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beheerpincode voor onbemande toestellen (Playscope: kioskmodus verlaten).
 * Enkel een hash (zie App\Domain\Setups\DeviceAdminPin), nooit de code zelf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'device_admin_pin')) {
                $table->string('device_admin_pin', 255)->nullable();
                $table->timestamp('device_admin_pin_updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['device_admin_pin', 'device_admin_pin_updated_at']);
        });
    }
};
