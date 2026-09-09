<?php

namespace App\Jobs;

use App\Models\PorteurProj;
use App\Models\Relance;
use App\Models\TypeRelance;
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

    public function handle(): void
    {
        $aujourdhui = Carbon::today();

        $projets = PorteurProj::whereNotNull('date_fin')
            ->whereNotIn('statut_validation', ['annule', 'resilie'])
            ->get();

        foreach ($projets as $pp) {
            $this->totalTraites++;
            $ancienNiveau  = $pp->niveau_alerte;
            $nouveauNiveau = $this->calculerNiveauAlerte($pp->date_fin, $aujourdhui);

            if ($ancienNiveau !== $nouveauNiveau) {
                $pp->niveau_alerte = $nouveauNiveau;
                $pp->save();
                $this->totalReclassifies++;

                match ($nouveauNiveau) {
                    'verte'  => $this->nouveauxVerte++,
                    'orange' => $this->nouveauxOrange++,
                    'rouge'  => $this->nouveauxRouge++,
                    default  => null,
                };

                $this->creerRelanceSiNecessaire($pp, $ancienNiveau, $nouveauNiveau, $aujourdhui);
            }
        }

        Log::info('ClassifyAlertsJob terminé', [
            'total_traites'      => $this->totalTraites,
            'total_reclassifies' => $this->totalReclassifies,
            'nouveaux_verte'     => $this->nouveauxVerte,
            'nouveaux_orange'    => $this->nouveauxOrange,
            'nouveaux_rouge'     => $this->nouveauxRouge,
            'relances_creees'    => $this->relancesCreees,
        ]);
    }

    protected function calculerNiveauAlerte($dateFin, Carbon $aujourdhui): string
    {
        $dateFin = Carbon::parse($dateFin);

        if ($aujourdhui->lessThanOrEqualTo($dateFin)) {
            return 'verte';
        }

        $joursDepasses = (int) $dateFin->diffInDays($aujourdhui);

        if ($joursDepasses >= 90) return 'rouge';
        if ($joursDepasses >= 60) return 'orange';
        if ($joursDepasses >= 30) return 'verte';

        return 'verte';
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
