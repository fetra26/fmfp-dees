<?php

namespace App\Filament\Widgets;

use App\Models\Benef;
use App\Models\Porteur;
use App\Models\PorteurProj;
use App\Models\Projet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * KPI principaux du dashboard SEER — vue synthétique du portefeuille.
 *
 * NB : Les alertes rouges/oranges/vertes sont affichées séparément par
 * AlertesWidget. Ici on montre uniquement les grands indicateurs.
 */
class StatsOverviewWidget extends BaseWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'stats_overview';

    protected static ?int $sort = 30;
    protected ?string $heading = "📊 Vue d'ensemble";
    protected ?string $description = "Portefeuille, bénéficiaires et allocation cumulée";

    protected function getStats(): array
    {
        $data = Cache::remember('widget.stats.overview', 300, function () {
            $statutsCloturesIds = DB::table('statut_projet')
                ->whereIn('code', ['cloture', 'annule'])
                ->pluck('id');

            $benef = DB::table('benef')
                ->selectRaw("
                    SUM(CASE WHEN type='prevu'   THEN total ELSE 0 END) AS prevu,
                    SUM(CASE WHEN type='realise' THEN total ELSE 0 END) AS realise,
                    SUM(CASE WHEN type='realise' THEN f ELSE 0 END)    AS f_realise,
                    SUM(CASE WHEN type='realise' THEN h ELSE 0 END)    AS h_realise
                ")->first();

            $benefPrevu   = (int) ($benef->prevu   ?? 0);
            $benefRealise = (int) ($benef->realise ?? 0);
            $fRealise     = (int) ($benef->f_realise ?? 0);
            $hRealise     = (int) ($benef->h_realise ?? 0);
            $totalGenre   = $fRealise + $hRealise;
            $tauxFemmes   = $totalGenre > 0 ? round($fRealise * 100 / $totalGenre) : 0;
            $tauxRealise  = $benefPrevu > 0 ? round($benefRealise * 100 / $benefPrevu) : 0;

            return [
                'total_projets'    => Projet::count(),
                'projets_actifs'   => Projet::whereNotIn('statut_projet_id', $statutsCloturesIds)->count(),
                'benef_prevu'      => $benefPrevu,
                'benef_realise'    => $benefRealise,
                'taux_realise'     => $tauxRealise,
                'taux_femmes'      => $tauxFemmes,
                'allocation_totale'=> (int) PorteurProj::sum('montant_total'),
                'total_porteurs'   => Porteur::count(),
            ];
        });

        $fmt = fn ($n) => number_format($n, 0, ',', ' ');

        return [
            Stat::make('Projets', $fmt($data['projets_actifs']))
                ->description($fmt($data['total_projets']) . ' projets au total (actifs + clôturés)')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->chart([1, 2, 3, 4, 5, 6, 7]),

            Stat::make('Bénéficiaires', $fmt($data['benef_realise']))
                ->description(
                    $data['benef_prevu'] > 0
                        ? "Prévu : {$fmt($data['benef_prevu'])} · Atteint : {$data['taux_realise']}%"
                        : 'Aucun bénéficiaire enregistré'
                )
                ->descriptionIcon('heroicon-m-users')
                ->icon('heroicon-o-users')
                ->color($data['taux_realise'] >= 80 ? 'success' : ($data['taux_realise'] >= 50 ? 'warning' : 'gray')),

            Stat::make('Allocation cumulée', $fmt($data['allocation_totale']) . ' Ar')
                ->description('Total conventions signées')
                ->descriptionIcon('heroicon-m-banknotes')
                ->icon('heroicon-o-banknotes')
                ->color('info'),

            Stat::make('Équité femmes', $data['taux_femmes'] . '%')
                ->description($fmt($data['total_porteurs']) . ' entreprises porteuses')
                ->descriptionIcon('heroicon-m-scale')
                ->icon('heroicon-o-user-group')
                ->color($data['taux_femmes'] >= 40 ? 'success' : ($data['taux_femmes'] >= 25 ? 'warning' : 'danger')),
        ];
    }
}
