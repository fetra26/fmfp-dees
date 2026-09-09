<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('district', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('region')->cascadeOnDelete();
            $table->string('libelle', 150);
            $table->string('libelle_normalise', 150)->index();
            $table->timestamps();

            $table->unique(['region_id', 'libelle_normalise'], 'uniq_district_region_lib');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district');
    }
};
