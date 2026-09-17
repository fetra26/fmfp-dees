<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Invalidation des compteurs mémorisés du tableau de bord.
 *
 * Chaque widget met ses chiffres en cache 5 minutes, ce qui évite de
 * recalculer des agrégats coûteux à chaque affichage. Mais après un import ou
 * une purge, le tableau de bord continuait d'afficher les anciens chiffres
 * pendant tout ce délai — donnant l'impression que l'import n'avait rien fait,
 * ou qu'une purge n'avait pas eu lieu.
 *
 * On purge donc explicitement après toute opération qui modifie massivement
 * les données.
 *
 * Les tags de cache auraient été plus élégants, mais ils ne fonctionnent pas
 * avec le driver « file » utilisé en développement — d'où cette liste
 * explicite. Un test vérifie qu'aucune clé de widget n'y manque.
 */
class CacheTableauDeBord
{
    /**
     * Toutes les clés mémorisées par les widgets.
     *
     * @var string[]
     */
    public const CLES = [
        'widget.alertes.counts',
        'widget.bilan.financier',
        'widget.chart.beneficiaires',
        'widget.chart.financement_guichet',
        'widget.chart.prevu_realise',
        'widget.chart.regions',
        'widget.chart.secteurs',
        'widget.chart.statuts',
        'widget.paiements_attente.ids',
        'widget.performance.beneficiaires',
        'widget.stats.overview',
        'widget.stats.projets_soumis',
    ];

    /** Oublie tous les compteurs, pour que le prochain affichage recalcule. */
    public static function vider(): void
    {
        foreach (self::CLES as $cle) {
            Cache::forget($cle);
        }
    }
}
