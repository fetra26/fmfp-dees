<?php

namespace App\Filament\Widgets;

use App\Models\PorteurProj;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AlertesWidget extends BaseWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'alertes';

    protected static ?int $sort = 10;
    protected ?string $heading = "🚦 Alertes de suivi";
    protected ?string $description = "Projets en retard sur la date de fin de convention";

    protected function getStats(): array
    {
        // Une seule requête SQL pour compter les 3 niveaux d'alerte
        $data = Cache::remember('widget.alertes.counts', 300, function () {
            $counts = PorteurProj::selectRaw('
                SUM(CASE WHEN niveau_alerte = "rouge"  THEN 1 ELSE 0 END) as rouge,
                SUM(CASE WHEN niveau_alerte = "orange" THEN 1 ELSE 0 END) as orange,
                SUM(CASE WHEN niveau_alerte = "verte"  THEN 1 ELSE 0 END) as verte
            ')->first();

            return [
                'rouge'  => (int) ($counts->rouge ?? 0),
                'orange' => (int) ($counts->orange ?? 0),
                'verte'  => (int) ($counts->verte ?? 0),
            ];
        });

        $total = $data['rouge'] + $data['orange'] + $data['verte'];

        return [
            Stat::make('🔴 Alertes rouges', $data['rouge'])
                ->description($data['rouge'] > 0 ? 'Procédure de résiliation' : 'Aucune alerte critique')
                ->descriptionIcon($data['rouge'] > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($data['rouge'] > 0 ? 'danger' : 'success')
                ->url('/admin/porteur-projs?tableFilters[niveau_alerte][value]=rouge'),

            Stat::make('🟠 Alertes oranges', $data['orange'])
                ->description('Mise en demeure (60-89j)')
                ->descriptionIcon('heroicon-m-envelope')
                ->color($data['orange'] > 0 ? 'warning' : 'gray')
                ->url('/admin/porteur-projs?tableFilters[niveau_alerte][value]=orange'),

            Stat::make('🟢 Alertes vertes', $data['verte'])
                ->description('Relance préventive (30-59j)')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color('info')
                ->url('/admin/porteur-projs?tableFilters[niveau_alerte][value]=verte'),

            Stat::make('Total surveillance', $total)
                ->description('Projets avec alerte active')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),
        ];
    }
}
