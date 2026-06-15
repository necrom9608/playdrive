<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * uurrooster v1 (V016)
 *
 * Twee tabellen voor het personeels-uurrooster:
 *
 *  - roster_templates : het ALGEMENE weekrooster per medewerker. Eén rij per
 *    (tenant, medewerker, weekdag, block_index). Meerdere blokken per dag
 *    mogelijk (bv. 09:00-12:00 en 13:00-17:00). Weekdag 1=maandag .. 7=zondag
 *    (ISO 8601), gelijk aan opening_hours.
 *
 *  - roster_shifts : de CONCRETE shiften per kalenderdag. Worden uit het
 *    template gegenereerd per week, maar kunnen daarna per week vrij aangepast
 *    worden. `source` markeert herkomst ('template' | 'manual'), `is_edited`
 *    markeert een uit-template-shift die handmatig gewijzigd is, zodat
 *    regeneratie hem niet overschrijft.
 *
 * Tijden als TIME (lokaal, zoals opening_hours) — geen UTC-conversie nodig.
 * Geen foreign key constraints — compatibel met MyISAM en InnoDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roster_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedTinyInteger('weekday');         // 1=ma .. 7=zo
            $table->unsignedTinyInteger('block_index')->default(0);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('label', 100)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'weekday', 'block_index'], 'roster_tpl_unique');
        });

        Schema::create('roster_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('status', 20)->default('scheduled'); // scheduled|confirmed|cancelled
            $table->string('source', 20)->default('template');  // template|manual
            $table->boolean('is_edited')->default(false);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
            $table->index(['tenant_id', 'user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_shifts');
        Schema::dropIfExists('roster_templates');
    }
};
