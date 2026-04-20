<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * opening-hours v1
 * Aanmaken van de opening_exceptions tabel.
 * Bevat specifieke datums waarop de tenant afwijkt van het normale schema.
 * Kan zowel extra open dagen als extra gesloten dagen bevatten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->boolean('is_open')->default(false);
            $table->time('open_from')->nullable();
            $table->time('open_until')->nullable();
            $table->string('label', 100)->nullable();   // bijv. "Feestdag", "Extra dag"
            $table->timestamps();

            $table->unique(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_exceptions');
    }
};
