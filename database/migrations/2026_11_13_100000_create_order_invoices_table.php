<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturen via WeFact.
 *
 * - order_invoices: één factuur per afgerekende bestelling. Klantgegevens (zoals op de factuur),
 *   status en de verwijzing naar WeFact. Elke stap wordt bijgehouden zodat "opnieuw" verdergaat
 *   waar het stopte: aangemaakt (wefact_invoice_id) → verstuurd (sent_at) → betaald (paid_marked_at).
 * - tenants.wefact_api_key (versleuteld) en tenants.wefact_tax_codes (btw-% → WeFact btw-code).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_invoices')) {
            Schema::create('order_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('order_id')->unique();
                $table->string('status', 20)->default('pending');   // pending | missing_details | error | done
                $table->string('channel', 10)->nullable();           // peppol | email
                $table->string('company_name')->nullable();
                $table->string('contact_name')->nullable();
                $table->string('vat_number', 40)->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->string('city')->nullable();
                $table->string('country', 2)->default('BE');
                $table->string('wefact_debtor_code', 50)->nullable();
                $table->unsignedBigInteger('wefact_invoice_id')->nullable();
                $table->string('wefact_invoice_code', 50)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('paid_marked_at')->nullable();
                $table->text('error')->nullable();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'wefact_api_key')) {
                $table->text('wefact_api_key')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'wefact_tax_codes')) {
                $table->json('wefact_tax_codes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_invoices');

        Schema::table('tenants', function (Blueprint $table) {
            foreach (['wefact_api_key', 'wefact_tax_codes'] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
