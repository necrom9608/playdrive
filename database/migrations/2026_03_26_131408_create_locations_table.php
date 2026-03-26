<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('country', 2)->default('BE');
            $table->string('postal_code', 10);
            $table->string('city');
            $table->timestamps();

            $table->index('postal_code');
            $table->index('city');
            $table->index(['postal_code', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
