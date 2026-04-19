<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();

            // Tenant context (nullable voor platform-brede mails)
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            // Ontvanger
            $table->string('to_email');
            $table->string('to_name')->nullable();

            // Optionele koppeling met account
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();

            // Mail inhoud
            $table->string('subject');
            $table->string('mail_type', 100)->nullable()->index(); // bv. 'member_invite', 'password_reset', ...
            $table->longText('html_body')->nullable();

            // Resend tracking
            $table->string('resend_id', 100)->nullable()->unique()->index();

            // Status — bijgewerkt via webhook
            $table->string('status', 50)->default('queued')->index();
            // queued | sent | delivered | opened | clicked | bounced | complained | failed

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();

            // Bounce/complaint details
            $table->string('bounce_type', 50)->nullable();   // hard | soft
            $table->text('bounce_description')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('to_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
