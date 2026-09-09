<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commune', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('region')->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('district')->nullOnDelete();
            $table->string('libelle', 150);
            $table->string('libelle_normalise', 150)->index();
            $table->timestamps();

            $table->unique(['region_id', 'libelle_normalise'], 'uniq_commune_region_lib');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commune');
    }
};
