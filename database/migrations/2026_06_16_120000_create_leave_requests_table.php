<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verlofaanvragen (V019).
 *
 * Een medewerker vraagt verlof aan voor een periode (start_date..end_date) via
 * de staff-app. De backoffice keurt goed of af. Bij het beoordelen toont de
 * backoffice een waarschuwing wanneer de periode samenvalt met shiften waarin
 * de persoon al is ingepland (conflict).
 *
 * Geen foreign keys — compatibel met MyISAM en InnoDB (zoals de roster-tabellen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|cancelled
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
