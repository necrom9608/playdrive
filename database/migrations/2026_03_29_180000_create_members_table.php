<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('first_name', 100);
            $table->string('last_name', 100);

            $table->string('street')->nullable();
            $table->string('house_number', 20)->nullable();
            $table->string('box', 20)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();

            $table->string('email')->nullable();
            $table->string('login', 100)->nullable();
            $table->string('password')->nullable();
            $table->string('rfid_uid', 100)->nullable();

            $table->text('comment')->nullable();

            $table->date('membership_starts_at')->nullable();
            $table->date('membership_ends_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'last_name', 'first_name'], 'members_tenant_name_index');
            $table->index(['tenant_id', 'membership_ends_at'], 'members_tenant_membership_end_index');
            $table->index(['tenant_id', 'email'], 'members_tenant_email_index');
            $table->index(['tenant_id', 'login'], 'members_tenant_login_index');
            $table->index(['tenant_id', 'rfid_uid'], 'members_tenant_rfid_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
