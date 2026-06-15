<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * uurrooster v2 (V017) — herwerking naar slot-gebaseerd model.
 *
 * Het rooster verschuift van "blok hangt aan een persoon" (V016) naar
 * "blok staat op zichzelf en personen hangen eronder":
 *
 *  - roster_roles        : beheerde rollenlijst per tenant (bar, onthaal, ...),
 *                          met kleur en actief/inactief i.p.v. verwijderen.
 *  - roster_slots        : het ALGEMENE rooster = de "vraag" per
 *                          season_key x weekdag: tijd, rol, gewenst aantal,
 *                          staand commentaar.
 *  - roster_slot_defaults: standaard-invullers per slot (0..N).
 *  - roster_shifts       : de UITGEROLDE blokken per kalenderdag. Velden van
 *                          het slot worden bij genereren gesnapshot zodat een
 *                          al geplande week niet verschuift als het sjabloon
 *                          later wijzigt.
 *  - roster_assignments  : persoon-in-blok. De rol volgt uit de shift.
 *
 * season_key + RegionSeason bepalen per dag welk rooster geldt (vakantie =
 * eigen set slots), gelijk aan de openingsuren. Tijden als TIME (lokaal).
 * Geen foreign key constraints — compatibel met MyISAM en InnoDB.
 *
 * Schone herwerking: de V016-tabellen (roster_templates, roster_shifts oud
 * schema) worden gedropt. down() herstelt ze NIET.
 */
return new class extends Migration
{
    public function up(): void
    {
        // V016 opruimen (oud schema).
        Schema::dropIfExists('roster_shifts');
        Schema::dropIfExists('roster_templates');

        Schema::create('roster_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name', 100);
            $table->string('color', 20)->nullable();   // hex, bv. #38bdf8
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('roster_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('season_key', 50);
            $table->unsignedTinyInteger('weekday');             // 1=ma .. 7=zo
            $table->unsignedBigInteger('role_id')->nullable()->index();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('desired_count')->nullable();
            $table->string('comment', 255)->nullable();          // staand commentaar
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'season_key', 'weekday']);
        });

        Schema::create('roster_slot_defaults', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('slot_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();

            $table->unique(['slot_id', 'user_id']);
        });

        Schema::create('roster_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->date('date');
            $table->string('season_key', 50)->nullable();
            $table->unsignedBigInteger('slot_id')->nullable()->index();  // herkomst
            $table->unsignedBigInteger('role_id')->nullable()->index();  // snapshot
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('desired_count')->nullable();
            $table->string('comment', 255)->nullable();   // gesnapshot staand commentaar
            $table->string('note', 255)->nullable();      // eenmalige dagnotitie
            $table->string('status', 20)->default('scheduled');
            $table->string('source', 20)->default('template'); // template|manual
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
        });

        Schema::create('roster_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('shift_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('source', 20)->default('manual'); // template|manual
            $table->timestamps();

            $table->unique(['shift_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_assignments');
        Schema::dropIfExists('roster_shifts');
        Schema::dropIfExists('roster_slot_defaults');
        Schema::dropIfExists('roster_slots');
        Schema::dropIfExists('roster_roles');
    }
};
