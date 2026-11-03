<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maandprestaties (overname naar sociaal secretariaat, bv. Liantis).
 *
 *  - staff_absence_days : afwezigheidscode per medewerker per dag (F, V, KV, Z, ...)
 *  - staff_payroll_months : "ingevoerd in Liantis"-status per medewerker per maand,
 *    met een snapshot zodat latere wijzigingen gemeld kunnen worden.
 *
 * Geen foreign keys — zelfde stijl als roster/leave-tabellen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_absence_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->string('code', 10);
            $table->unsignedInteger('minutes')->nullable(); // optioneel aantal uren (in minuten)
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'date']);
        });

        Schema::create('staff_payroll_months', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->char('period', 7); // YYYY-MM
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->string('snapshot_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_payroll_months');
        Schema::dropIfExists('staff_absence_days');
    }
};
