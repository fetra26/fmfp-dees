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

                    $nouveauNiveau = $this->estSousSuivi($pp)
                        ? $this->calculerNiveauAlerte($pp->date_fin, $aujourdhui)
                        : null;

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

    /**
     * Statuts qui retirent un projet du suivi des alertes.
     *
     * Un dossier clôturé, annulé ou résilié n'a plus à être relancé : c'est
     * précisément l'objet de ce suivi que de poursuivre les conventions dont
     * l'échéance est passée SANS que le projet soit clos.
     */
    protected const STATUTS_HORS_SUIVI = ['cloture', 'fini_cloture', 'annule', 'resilie'];

    /**
     * Ce projet doit-il encore être surveillé ?
     *
     * Deux façons d'en sortir, retenues avec la DEES : le statut prononcé, ou
     * le versement intégral des tranches. La seconde évite de relancer un
     * porteur déjà soldé dont le statut n'aurait pas encore été mis à jour.
     */
    protected function estSousSuivi(PorteurProj $pp): bool
    {
        if (blank($pp->date_fin)) {
            return false;
        }

        if (in_array($pp->statut_validation, self::STATUTS_HORS_SUIVI, true)) {
            return false;
        }

        return ! $this->tranchesSoldees($pp);
    }

    /**
     * Les tranches sont-elles versées ?
     *
     * J1 et J2 suffisent : le versement se fait généralement en deux jalons,
     * le J3 n'existant que sur les cas équité. L'exiger laisserait la plupart
     * des projets éternellement sous suivi.
     */
    protected function tranchesSoldees(PorteurProj $pp): bool
    {
        $tranches = $pp->paiements
            ->where('is_annule', false)
            ->pluck('ligne')
            ->unique();

        return $tranches->contains('J1') && $tranches->contains('J2');
    }

    /**
     * Niveau d'alerte, ou null si le projet n'est pas en alerte.
     *
     * Renvoyer null plutôt que 'verte' en deçà de 30 jours est le cœur de la
     * correction : 'verte' désignait aussi bien un projet à l'heure qu'un
     * retard de 30 à 59 jours appelant une relance. Les deux étaient donc
     * impossibles à distinguer, et le compteur « alertes vertes » du tableau
     * de bord affichait en réalité tout le portefeuille.
     */
    protected function calculerNiveauAlerte($dateFin, Carbon $aujourdhui): ?string
    {
        $dateFin = Carbon::parse($dateFin);

        if ($aujourdhui->lessThanOrEqualTo($dateFin)) {
            return null;
        }

        $joursDepasses = (int) $dateFin->diffInDays($aujourdhui);

        if ($joursDepasses >= 90) return 'rouge';
        if ($joursDepasses >= 60) return 'orange';
        if ($joursDepasses >= 30) return 'verte';

        return null;
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
