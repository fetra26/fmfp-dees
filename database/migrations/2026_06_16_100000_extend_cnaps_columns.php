<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Augmenter cnaps à 100 caractères : certaines lignes du fichier DEES_BDD
        // contiennent plusieurs CNaPS concaténés dans une seule cellule.
        Schema::table('porteur', function (Blueprint $table) {
            $table->string('cnaps', 100)->nullable()->change();
            $table->string('nif', 50)->nullable()->change();
        });

        Schema::table('partenaire', function (Blueprint $table) {
            $table->string('cnaps', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('porteur', function (Blueprint $table) {
            $table->string('cnaps', 30)->nullable()->change();
            $table->string('nif', 30)->nullable()->change();
        });

        Schema::table('partenaire', function (Blueprint $table) {
            $table->string('cnaps', 30)->nullable()->change();
        });
    }
};
