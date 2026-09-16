<?php

namespace App\Filament\Widgets;

use App\Models\Benef;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PerformanceBeneficiairesWidget extends BaseWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'performance_benef';

    protected static ?int $sort = 50;
    protected ?string $heading = "👥 Performance bénéficiaires (Prévu vs Réalisé)";

    protected function getStats(): array
    {
        $data = Cache::remember('widget.performance.beneficiaires', 300, function () {
            // Une seule requête pour les deux types (prévu + réalisé)
            $stats = Benef::selectRaw('
                type,
                SUM(total) as total, SUM(h) as h, SUM(f) as f,
                SUM(jeunes) as jeunes, SUM(fpe) as fpe, SUM(cadres) as cadres
            ')
                ->groupBy('type')
                ->get()
                ->keyBy('type');

            return [
                'prevu' => $stats->get('prevu'),
                'real'  => $stats->get('realise'),
            ];
        });

        $prevu = $data['prevu'];
        $real  = $data['real'];

        $totalPrevu = (int) ($prevu->total ?? 0);
        $totalReal  = (int) ($real->total ?? 0);
        $taux = $totalPrevu > 0 ? round(($totalReal / $totalPrevu) * 100, 1) : 0;

        return [
            Stat::make('Total bénéficiaires', number_format($totalReal, 0, ',', ' '))
                ->description("Prévu : " . number_format($totalPrevu, 0, ',', ' ') . " — Taux : {$taux}%")
                ->descriptionIcon($taux >= 75 ? 'heroicon-m-check-circle' : 'heroicon-m-clock')
                ->color($taux >= 75 ? 'success' : ($taux >= 50 ? 'warning' : 'danger')),

            Stat::make('Hommes', number_format($real->h ?? 0, 0, ',', ' '))
                ->description("Prévu : " . number_format($prevu->h ?? 0, 0, ',', ' '))
                ->icon('heroicon-o-user')
                ->color('info'),

            Stat::make('Femmes', number_format($real->f ?? 0, 0, ',', ' '))
                ->description("Prévu : " . number_format($prevu->f ?? 0, 0, ',', ' '))
                ->icon('heroicon-o-user')
                ->color('warning'),

            Stat::make('Jeunes', number_format($real->jeunes ?? 0, 0, ',', ' '))
                ->description("Prévu : " . number_format($prevu->jeunes ?? 0, 0, ',', ' '))
                ->icon('heroicon-o-academic-cap')
                ->color('success'),

            Stat::make('FPE (Formation pré-emploi)', number_format($real->fpe ?? 0, 0, ',', ' '))
                ->description("Prévu : " . number_format($prevu->fpe ?? 0, 0, ',', ' '))
                ->icon('heroicon-o-briefcase')
                ->color('primary'),

            Stat::make('Femmes cadres', number_format($real->cadres ?? 0, 0, ',', ' '))
                ->description("Prévu : " . number_format($prevu->cadres ?? 0, 0, ',', ' '))
                ->icon('heroicon-o-star')
                ->color('warning'),
        ];
    }

    public function getColumns(): int
    {
        return 3;
    }
}
