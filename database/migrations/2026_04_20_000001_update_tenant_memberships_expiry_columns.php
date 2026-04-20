<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table) {
            // Hernoem de bestaande kolom naar 14d-variant
            $table->renameColumn('expiry_warning_mail_sent_at', 'expiry_warning_14d_mail_sent_at');

            // Voeg de ontbrekende kolommen toe
            $table->timestamp('expiry_warning_7d_mail_sent_at')->nullable()->after('expiry_warning_14d_mail_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->renameColumn('expiry_warning_14d_mail_sent_at', 'expiry_warning_mail_sent_at');
            $table->dropColumn('expiry_warning_7d_mail_sent_at');
        });
    }
};
