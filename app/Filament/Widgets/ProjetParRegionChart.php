<?php

namespace App\Filament\Widgets;

use App\Models\Projet;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class ProjetParRegionChart extends ChartWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'projet_par_region';

    protected ?string $heading = "🗺 Projets par région";
    protected static ?int $sort = 80;
    protected static bool $isLazy = true;

    protected function getData(): array
    {
        return Cache::remember('widget.chart.regions', 300, function () {
            $data = Projet::join('region', 'projet.region_id', '=', 'region.id')
                ->selectRaw('region.libelle as region, COUNT(*) as total')
                ->groupBy('region.libelle')
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            return [
                'datasets' => [[
                    'label'           => 'Projets',
                    'data'            => $data->pluck('total')->toArray(),
                    'backgroundColor' => ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#84cc16','#f97316','#ec4899','#6b7280'],
                ]],
                'labels' => $data->pluck('region')->toArray(),
            ];
        });
    }

    protected function getType(): string { return 'bar'; }
}
