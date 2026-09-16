<?php

namespace App\Filament\Widgets;

use App\Services\CategorisationProjets;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Répartition des projets soumis : notifié, engagé, refusé, annulé, clôturé.
 *
 * Répond au contrôle de cohérence de la DEES :
 *   soumis = notifié + engagé + refusé + annulé + clôturé
 *
 * L'égalité est garantie par construction, la cascade de CategorisationProjets
 * étant exhaustive. Ce qu'il faut surveiller n'est donc pas un écart, mais la
 * taille de « Soumis sans suite » : ces projets sont bien dans la base, sans
 * aucun fait daté permettant de les situer dans le circuit. Un nombre élevé
 * signale un fichier incomplet plutôt qu'une erreur de calcul.
 */
class ProjetsSoumisWidget extends BaseWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'projets_soumis';

    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $data = Cache::remember('widget.stats.projets_soumis', 300, function () {
            return [
                'total'   => CategorisationProjets::totalSoumis(),
                'comptes' => CategorisationProjets::compter(),
            ];
        });

        $total   = $data['total'];
        $c       = $data['comptes'];
        $fmt     = fn (int $n) => number_format($n, 0, ',', ' ');
        $part    = fn (int $n) => $total > 0 ? round($n * 100 / $total) . ' %' : '—';
        $ouverts = $c['notifie'] + $c['engage'];

        return [
            Stat::make('Projets soumis', $fmt($total))
                ->description('Total des lignes importées')
                ->descriptionIcon('heroicon-m-inbox-stack')
                ->icon('heroicon-o-inbox-stack')
                ->color('primary'),

            Stat::make('Notifié', $fmt($c['notifie']))
                ->description($part($c['notifie']) . ' — notifiés, pas encore engagés')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('info'),

            Stat::make('Engagé', $fmt($c['engage']))
                ->description($part($c['engage']) . ' — un montant est engagé')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Refusé', $fmt($c['refuse']))
                ->description($part($c['refuse']))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Annulé', $fmt($c['annule']))
                ->description($part($c['annule']) . ' — annulés ou résiliés')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('gray'),

            Stat::make('Clôturé', $fmt($c['cloture']))
                ->description($part($c['cloture']))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Non clôturé', $fmt($ouverts))
                ->description('Notifiés et engagés encore ouverts')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            // Le seul indicateur qui demande une action : ces projets sont en
            // base sans date de notification, sans montant, sans statut
            // exploitable. Ils échappent donc à tout suivi.
            Stat::make('Soumis sans suite', $fmt($c['soumis_seul']))
                ->description(
                    $c['soumis_seul'] === 0
                        ? 'Tous les projets sont situés dans le circuit'
                        : 'Aucun fait daté : à compléter'
                )
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color($c['soumis_seul'] === 0 ? 'success' : 'danger'),
        ];
    }
}
