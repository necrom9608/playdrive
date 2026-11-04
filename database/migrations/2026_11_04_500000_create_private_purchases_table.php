<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aankopen van particulieren: aankoopborderel dat de verkoper zelf invult en digitaal ondertekent.
        Schema::create('private_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();

            // Nummering per tenant per jaar: AK-2026-0001
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->string('number', 30);

            $table->string('status', 20)->default('draft'); // draft | sent | signed | paid | cancelled

            // Publieke link voor de verkoper (QR aan de balie of per mail)
            $table->string('token', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('sent_via', 20)->nullable();    // qr | email
            $table->string('invite_email')->nullable();    // adres waarnaar de link gemaild werd
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('opened_at')->nullable();

            // Wat er aangekocht wordt
            $table->json('items');                         // [{description, quantity, unit_price}]
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('payment_method', 20)->default('transfer'); // transfer | cash
            $table->date('purchase_date');
            $table->string('sign_place', 100)->nullable();
            $table->json('photos')->nullable();            // [{path, name}] op de private disk
            $table->text('internal_note')->nullable();

            // Wie krijgt de PDF na ondertekening
            $table->boolean('mail_seller')->default(true);
            $table->boolean('mail_tenant')->default(true);
            $table->boolean('mail_accounting')->default(true);
            $table->json('extra_recipients')->nullable();

            // Door de verkoper ingevuld
            $table->string('seller_first_name', 100)->nullable();
            $table->string('seller_last_name', 100)->nullable();
            $table->string('seller_street', 190)->nullable();
            $table->string('seller_postal_code', 20)->nullable();
            $table->string('seller_city', 100)->nullable();
            $table->string('seller_country', 100)->nullable();
            $table->string('seller_email')->nullable();
            $table->string('seller_phone', 50)->nullable();
            $table->string('seller_iban', 40)->nullable();
            $table->string('seller_iban_holder', 190)->nullable();
            $table->json('declarations')->nullable();
            $table->json('signature')->nullable();         // {w, h, strokes: [[[x,y],...]]}

            // Bewijs van ondertekening
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip', 45)->nullable();
            $table->string('signed_user_agent', 500)->nullable();
            $table->string('document_hash', 64)->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_hash', 64)->nullable();

            // Verzending PDF
            $table->timestamp('mailed_at')->nullable();
            $table->json('mailed_to')->nullable();
            $table->text('mail_error')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'year', 'sequence']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_purchases');
    }
};
