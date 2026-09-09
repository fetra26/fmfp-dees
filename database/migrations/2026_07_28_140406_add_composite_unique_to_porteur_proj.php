<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clé composite pour empêcher les vrais doublons au niveau relation :
        // même projet + même porteur + même convention = 1 seule entrée
        // (multi-porteurs légitimes = même projet/conv + porteurs différents = entrées séparées)
        Schema::table('porteur_proj', function (Blueprint $table) {
            $table->unique(
                ['projet_id', 'porteur_id', 'reference_convention_normalisee'],
                'uniq_pp_composite'
            );
        });
    }

    public function down(): void
    {
        Schema::table('porteur_proj', function (Blueprint $table) {
            $table->dropUnique('uniq_pp_composite');
        });
    }
};
