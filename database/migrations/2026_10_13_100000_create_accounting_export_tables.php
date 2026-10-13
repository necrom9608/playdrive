<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Instellingen per tenant: of, wanneer en naar wie de dagtotalen gemaild worden.
        Schema::create('accounting_export_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();

            $table->boolean('enabled')->default(false);
            $table->timestamp('enabled_at')->nullable(); // automatisch versturen telt pas vanaf dit moment
            $table->json('recipients')->nullable();      // ["boekhouder@kantoor.be", ...]
            $table->json('cc')->nullable();
            $table->string('frequency', 20)->default('quarter'); // month | quarter
            $table->unsignedTinyInteger('send_day')->default(5); // dag van de maand na het einde van de periode (1-28)
            $table->boolean('include_invoices')->default(false);
            $table->boolean('include_payment_methods')->default(true);
            $table->json('visible_groups')->nullable();  // {"rate0":false,"rate6":true,...}
            $table->text('message')->nullable();         // extra tekst in de mail

            $table->timestamps();
        });

        // Logboek: elke verzending (automatisch of manueel), ook mislukte.
        Schema::create('accounting_exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();

            $table->string('period_type', 20);           // month | quarter | range
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_label', 100);

            $table->string('trigger', 20);               // auto | manual
            $table->json('recipients');
            $table->json('cc')->nullable();
            $table->string('status', 20);                // sent | failed
            $table->text('error')->nullable();

            $table->decimal('total_incl_vat', 12, 2)->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->string('filename')->nullable();

            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_exports');
        Schema::dropIfExists('accounting_export_settings');
    }
};
