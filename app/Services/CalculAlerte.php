<?php

namespace App\Services;

use App\Models\PorteurProj;
use Illuminate\Support\Carbon;

/**
 * Règle unique du niveau d'alerte d'un projet.
 *
 * Extraite du job pour être appliquée AUSSI à l'enregistrement d'un projet :
 * le job ne tourne que la nuit, or rouvrir un dossier clôturé doit le faire
 * réapparaître immédiatement dans les alertes, pas le lendemain.
 *
 * ── La règle ──
 *
 * Un projet est sous suivi tant que sa convention est échue SANS qu'il soit
 * clos. En sortent les dossiers clôturés, annulés, résiliés, et ceux dont les
 * tranches J1 et J2 sont versées — le versement intégral valant clôture de
 * fait, même quand le statut n'a pas été mis à jour.
 *
 *   null     moins de 30 jours de retard, ou hors suivi
 *   verte    30 à 59 jours   première relance préventive
 *   orange   60 à 89 jours   deuxième relance et mise en demeure
 *   rouge    90 jours et +   procédure de résiliation
 *
 * null signifie « aucune alerte ». Auparavant la colonne était NOT NULL avec
 * 'verte' par défaut, si bien qu'un projet à l'heure et un retard de 30 jours
 * portaient la même valeur.
 */
class CalculAlerte
{
    /** Statuts qui retirent un projet du suivi. */
    public const STATUTS_HORS_SUIVI = ['cloture', 'fini_cloture', 'annule', 'resilie'];

    /**
     * Niveau d'alerte d'un projet, ou null s'il n'est pas en alerte.
     *
     * @param  bool  $paiementsCharges  true quand la relation paiements est déjà
     *                                  chargée, pour éviter une requête par ligne
     *                                  lors d'un traitement en masse.
     */
    public static function niveauPour(PorteurProj $pp, ?Carbon $aujourdhui = null, bool $paiementsCharges = false): ?string
    {
        if (! self::estSousSuivi($pp, $paiementsCharges)) {
            return null;
        }

        return self::niveauSelonRetard($pp->date_fin, $aujourdhui ?? Carbon::today());
    }

    /** Ce projet doit-il encore être surveillé ? */
    public static function estSousSuivi(PorteurProj $pp, bool $paiementsCharges = false): bool
    {
        if (blank($pp->date_fin)) {
            return false;
        }

        // Le statut est vérifié en premier : sur un dossier déjà clos, il évite
        // la requête sur les paiements.
        if (in_array($pp->statut_validation, self::STATUTS_HORS_SUIVI, true)) {
            return false;
        }

        return ! self::tranchesSoldees($pp, $paiementsCharges);
    }

    /**
     * Les tranches sont-elles versées ?
     *
     * J1 et J2 suffisent : le versement se fait généralement en deux jalons, le
     * J3 n'existant que sur les cas équité. L'exiger laisserait la plupart des
     * projets éternellement sous suivi.
     */
    public static function tranchesSoldees(PorteurProj $pp, bool $paiementsCharges = false): bool
    {
        if (! $pp->exists) {
            return false;
        }

        $tranches = $paiementsCharges
            ? $pp->paiements->where('is_annule', false)->pluck('ligne')
            : $pp->paiements()->where('is_annule', false)->pluck('ligne');

        $tranches = $tranches->unique();

        return $tranches->contains('J1') && $tranches->contains('J2');
    }

    /** Niveau selon les jours écoulés depuis l'échéance, null en deçà de 30. */
    public static function niveauSelonRetard(mixed $dateFin, ?Carbon $aujourdhui = null): ?string
    {
        if (blank($dateFin)) {
            return null;
        }

        $aujourdhui ??= Carbon::today();
        $dateFin = Carbon::parse($dateFin);

        if ($aujourdhui->lessThanOrEqualTo($dateFin)) {
            return null;
        }

        $joursDepasses = (int) $dateFin->diffInDays($aujourdhui);

        return match (true) {
            $joursDepasses >= 90 => 'rouge',
            $joursDepasses >= 60 => 'orange',
            $joursDepasses >= 30 => 'verte',
            default              => null,
        };
    }
}
