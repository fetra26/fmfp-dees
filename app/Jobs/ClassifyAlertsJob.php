<?php

namespace App\Jobs;

use App\Models\PorteurProj;
use App\Models\Relance;
use App\Models\TypeRelance;
use App\Services\CalculAlerte;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Job quotidien qui classifie tous les projets selon le niveau d'alerte
 * basé sur le nombre de jours écoulés depuis la date de fin de convention.
 *
 * Règles métier DEES :
 *   Verte   : 30 à 59 jours dépassés  → Première relance préventive
 *   Orange  : 60 à 89 jours dépassés  → Deuxième relance + lettre de mise en demeure
 *   Rouge   : 90+ jours dépassés      → Procédure de résiliation
 */
class ClassifyAlertsJob implements ShouldQueue
{
    use Queueable;

    public int $totalTraites      = 0;
    public int $totalReclassifies = 0;
    public int $nouveauxVerte     = 0;
    public int $nouveauxOrange    = 0;
    public int $nouveauxRouge     = 0;
    public int $relancesCreees    = 0;

    /** Projets dont le niveau a été remis à null : clôturés, soldés ou rentrés dans les temps. */
    public int $sortiesDuSuivi     = 0;

    public function handle(): void
    {
        $aujourdhui = Carbon::today();

        // On parcourt TOUS les projets, y compris ceux qui sortent du suivi.
        // L'ancienne version les écartait de la requête : un projet passé au
        // rouge puis clôturé gardait donc son rouge indéfiniment, faute d'être
        // jamais réexaminé. Le job doit pouvoir REMETTRE À NULL un niveau
        // devenu caduc, pas seulement en attribuer un nouveau.
        PorteurProj::query()
            ->with(['paiements' => fn ($q) => $q->where('is_annule', false)])
            ->chunkById(500, function ($projets) use ($aujourdhui) {
                foreach ($projets as $pp) {
                    $this->totalTraites++;
                    $ancienNiveau = $pp->niveau_alerte;

                    // Règle portée par CalculAlerte, partagée avec le hook du
                    // modèle : rouvrir un dossier depuis l'interface doit le
                    // faire réapparaître aussitôt, sans attendre cette passe.
                    // paiementsCharges: true — la relation est préchargée plus
                    // haut, inutile d'interroger la base ligne à ligne.
                    $nouveauNiveau = CalculAlerte::niveauPour($pp, $aujourdhui, paiementsCharges: true);

                    if ($ancienNiveau === $nouveauNiveau) {
                        continue;
                    }

                    $pp->niveau_alerte = $nouveauNiveau;
                    $pp->save();
                    $this->totalReclassifies++;

                    match ($nouveauNiveau) {
                        'verte'  => $this->nouveauxVerte++,
                        'orange' => $this->nouveauxOrange++,
                        'rouge'  => $this->nouveauxRouge++,
                        default  => $this->sortiesDuSuivi++,
                    };

                    if ($nouveauNiveau !== null) {
                        $this->creerRelanceSiNecessaire($pp, $ancienNiveau ?? '', $nouveauNiveau, $aujourdhui);
                    }
                }
            });

        Log::info('ClassifyAlertsJob terminé', [
            'total_traites'      => $this->totalTraites,
            'total_reclassifies' => $this->totalReclassifies,
            'nouveaux_verte'     => $this->nouveauxVerte,
            'nouveaux_orange'    => $this->nouveauxOrange,
            'nouveaux_rouge'     => $this->nouveauxRouge,
            'relances_creees'    => $this->relancesCreees,
            'sorties_du_suivi'   => $this->sortiesDuSuivi,
        ]);
    }

    protected function creerRelanceSiNecessaire(PorteurProj $pp, string $ancien, string $nouveau, Carbon $aujourdhui): void
    {
        $projet = $pp->projet;
        if (! $projet) return;

        // Verte → première relance préventive
        if ($nouveau === 'verte' && blank($pp->date_relance_1)) {
            $pp->update(['date_relance_1' => $aujourdhui->toDateString()]);
            $this->creerRelance($projet->id, 'preventive', $aujourdhui,
                'Première relance préventive (alerte verte : 30-59 jours dépassés)');
            $this->relancesCreees++;
        }

        // Orange → mise en demeure + relance 2
        if ($nouveau === 'orange' && blank($pp->date_relance_2)) {
            $pp->update(['date_relance_2' => $aujourdhui->toDateString()]);
            $this->creerRelance($projet->id, 'mise_demeure', $aujourdhui,
                'Lettre de mise en demeure (alerte orange : 60-89 jours dépassés)');
            $this->relancesCreees++;
        }

        // Rouge → résiliation
        if ($nouveau === 'rouge' && blank($pp->date_resiliation)) {
            $this->creerRelance($projet->id, 'resiliation', $aujourdhui,
                'Procédure de résiliation envisagée (alerte rouge : 90+ jours dépassés)');
            $this->relancesCreees++;
        }
    }

    protected function creerRelance(int $projetId, string $codeType, Carbon $date, string $contenu): void
    {
        $type = TypeRelance::where('code', $codeType)->first();

        Relance::firstOrCreate(
            [
                'projet_id'       => $projetId,
                'type_relance_id' => $type?->id,
                'date_relance'    => $date->toDateString(),
            ],
            ['contenu' => $contenu]
        );
    }
}
