<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Augmenter reference à 100 caractères (certaines réfs DEES dépassent 50)
        Schema::table('projet', function (Blueprint $table) {
            $table->string('reference', 100)->change();
            $table->string('intitule', 500)->change();
        });

        // volume_horaire_total : passer en INT pour éviter overflow
        Schema::table('formation', function (Blueprint $table) {
            $table->unsignedInteger('volume_horaire_total')->default(0)->change();
        });

        Schema::table('module', function (Blueprint $table) {
            $table->string('intitule', 500)->change();
        });
    }

    public function down(): void
    {
        Schema::table('projet', function (Blueprint $table) {
            $table->string('reference', 50)->change();
            $table->string('intitule', 300)->change();
        });

        Schema::table('formation', function (Blueprint $table) {
            $table->unsignedSmallInteger('volume_horaire_total')->default(0)->change();
        });

        Schema::table('module', function (Blueprint $table) {
            $table->string('intitule', 300)->change();
        });
    }
};
