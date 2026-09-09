<?php

use App\Models\ImportMapping;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('porteur', function (Blueprint $table) {
            // Colonne pour matching rapide : ACM-NET, ACM NET, Acm Net → ACMNET
            $table->string('raison_sociale_normalisee', 200)
                ->nullable()
                ->after('raison_sociale')
                ->index('idx_porteur_raison_normalisee');
        });

        // Peupler la colonne pour les enregistrements existants
        DB::table('porteur')->orderBy('id')->chunk(200, function ($porteurs) {
            foreach ($porteurs as $p) {
                DB::table('porteur')->where('id', $p->id)->update([
                    'raison_sociale_normalisee' => ImportMapping::normaliser($p->raison_sociale),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('porteur', function (Blueprint $table) {
            $table->dropIndex('idx_porteur_raison_normalisee');
            $table->dropColumn('raison_sociale_normalisee');
        });
    }
};
