<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('membership_type', 50)->default('adult');
            $table->string('rfid_uid', 100)->nullable();
            $table->date('membership_starts_at')->nullable();
            $table->date('membership_ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('comment')->nullable();
            $table->timestamp('confirmation_mail_sent_at')->nullable();
            $table->timestamp('expiry_warning_mail_sent_at')->nullable();
            $table->timestamp('expired_mail_sent_at')->nullable();
            $table->timestamps();

            // rfid_uid moet uniek zijn per tenant
            $table->unique(['tenant_id', 'rfid_uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_memberships');
    }
};
