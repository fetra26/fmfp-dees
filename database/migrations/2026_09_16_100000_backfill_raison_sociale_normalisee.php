<?php

use App\Models\ImportMapping;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renseigne raison_sociale_normalisee pour tous les porteurs existants.
 *
 * La colonne avait bien été créée et initialisée en juillet
 * (2026_07_28_125443_add_raison_sociale_normalisee_to_porteur), mais le modèle
 * Porteur ne la déclarait pas dans $fillable : tous les porteurs créés ensuite
 * — import comme saisie manuelle — sont repartis avec une valeur nulle.
 *
 * Or c'est ce champ qui permet de retrouver une entreprise déjà enregistrée.
 * Nulle, la recherche échouait systématiquement et chaque import recréait
 * l'entreprise, d'où les doublons constatés.
 *
 * Le modèle calcule désormais ce champ à chaque enregistrement ; cette
 * migration rattrape l'existant.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('porteur')
            ->select('id', 'raison_sociale')
            ->whereNull('raison_sociale_normalisee')
            ->orderBy('id')
            ->chunkById(500, function ($porteurs) {
                foreach ($porteurs as $porteur) {
                    DB::table('porteur')
                        ->where('id', $porteur->id)
                        ->update([
                            'raison_sociale_normalisee' => ImportMapping::normaliser($porteur->raison_sociale),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Rien à défaire : remettre la colonne à null recréerait le défaut.
    }
};
