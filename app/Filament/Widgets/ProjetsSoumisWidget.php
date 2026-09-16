<?php

namespace App\Filament\Widgets;

use App\Services\CategorisationProjets;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Répartition des projets soumis selon la taxonomie DEES.
 *
 *   Soumis = Validé + Refusé + Non éligible + Incomplet
 *   Validé = Notifié + Non notifié
 *   Notifié = Engagé + Clôturé + Sans convention
 *
 * Les totaux intermédiaires sont affichés à côté de leurs composantes, pour
 * que la lecture suive l'arbre plutôt qu'une liste plate.
 */
class ProjetsSoumisWidget extends BaseWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'projets_soumis';

    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $data = Cache::remember('widget.stats.projets_soumis', 300, function () {
            $comptes = CategorisationProjets::compter();

            return [
                'total'   => CategorisationProjets::totalSoumis(),
                'comptes' => $comptes,
                'notifie' => CategorisationProjets::agregat($comptes, 'notifie'),
                'valide'  => CategorisationProjets::agregat($comptes, 'valide'),
            ];
        });

        $total = $data['total'];
        $c     = $data['comptes'];
        $fmt   = fn (int $n) => number_format($n, 0, ',', ' ');
        $part  = fn (int $n) => $total > 0 ? round($n * 100 / $total) . ' %' : '—';

        $stats = [
            Stat::make('Projets soumis', $fmt($total))
                ->description('Total des lignes importées')
                ->descriptionIcon('heroicon-m-inbox-stack')
                ->icon('heroicon-o-inbox-stack')
                ->color('primary'),

            Stat::make('Validé', $fmt($data['valide']))
                ->description($part($data['valide']) . ' — dont ' . $fmt($data['notifie']) . ' notifiés')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Engagé', $fmt($c['engage']))
                ->description($part($c['engage']) . ' — J1 versé, formation en cours')
                ->descriptionIcon('heroicon-m-play-circle')
                ->color('warning'),

            Stat::make('Clôturé', $fmt($c['cloture']))
                ->description($part($c['cloture']) . ' — J1 et J2 versés')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Sans convention', $fmt($c['sans_convention']))
                ->description($part($c['sans_convention']) . ' — aucun retour porteur')
                ->descriptionIcon('heroicon-m-document-minus')
                ->color('gray'),

            Stat::make('Non notifié', $fmt($c['non_notifie']))
                ->description($part($c['non_notifie']) . ' — validé, jamais notifié')
                ->descriptionIcon('heroicon-m-bell-slash')
                ->color('gray'),

            Stat::make('Refusé', $fmt($c['refuse']))
                ->description($part($c['refuse']) . ' — CSP ou AFD')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Non éligible', $fmt($c['non_eligible']))
                ->description($part($c['non_eligible']))
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('danger'),

            Stat::make('Incomplet', $fmt($c['incomplet']))
                ->description($part($c['incomplet']) . ' — attente pièces')
                ->descriptionIcon('heroicon-m-document-magnifying-glass')
                ->color('warning'),
        ];

        // Cas qu'aucune branche du schéma DEES ne couvre : notifié, convention
        // revenue, mais pas le moindre versement. Affiché seulement s'il en
        // existe, pour ne pas encombrer le tableau de bord quand tout est net.
        if ($c['notifie_sans_versement'] > 0) {
            $stats[] = Stat::make('Notifié, sans versement', $fmt($c['notifie_sans_versement']))
                ->description('Convention revenue, aucun J1 — à qualifier')
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color('info');
        }

        return $stats;
    }
}
