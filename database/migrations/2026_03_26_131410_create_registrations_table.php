<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Registration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();

            // klant
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('municipality')->nullable();

            // reservatie
            $table->foreignId('event_type_id')->nullable()->constrained()->nullOnDelete();
            $table->date('event_date')->nullable();
            $table->time('event_time')->nullable();
            $table->foreignId('stay_option_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catering_option_id')->nullable()->constrained()->nullOnDelete();

            // deelnemers
            $table->unsignedInteger('participants_children')->default(0);
            $table->unsignedInteger('participants_adults')->default(0);
            $table->unsignedInteger('participants_supervisors')->default(0);

            // extra
            $table->text('comment')->nullable();
            $table->json('stats')->nullable();

            // factuur
            $table->boolean('invoice_requested')->default(false);
            $table->string('invoice_company_name')->nullable();
            $table->string('invoice_vat_number')->nullable();
            $table->string('invoice_email')->nullable();
            $table->string('invoice_address')->nullable();
            $table->string('invoice_postal_code', 10)->nullable();
            $table->string('invoice_city')->nullable();

            // operationeel
            $table->string('status')->default(Registration::STATUS_NEW);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->unsignedInteger('played_minutes')->default(0);
            $table->integer('bill_total_cents')->default(0);
            $table->boolean('outside_opening_hours')->default(false);

            $table->timestamps();

            $table->index('event_date');
            $table->index('status');
            $table->index('name');
            $table->index('phone');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
