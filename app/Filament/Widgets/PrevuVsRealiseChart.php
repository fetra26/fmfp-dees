<?php

namespace App\Filament\Widgets;

use App\Models\Benef;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class PrevuVsRealiseChart extends ChartWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'prevu_vs_realise';

    protected ?string $heading = '📊 Bénéficiaires : Prévu vs Réalisé';
    protected static ?int $sort = 9;
    protected static bool $isLazy = true;

    protected function getData(): array
    {
        return Cache::remember('widget.chart.prevu_realise', 300, function () {
            // Une seule requête pour les deux types
            $stats = Benef::selectRaw('
                type,
                SUM(h) as h, SUM(f) as f, SUM(jeunes) as jeunes,
                SUM(fpe) as fpe, SUM(cadres) as cadres
            ')
                ->groupBy('type')
                ->get()
                ->keyBy('type');

            $prevu = $stats->get('prevu');
            $real  = $stats->get('realise');

            return [
                'datasets' => [
                    [
                        'label' => 'Prévu',
                        'data'  => [
                            (int) ($prevu->h ?? 0), (int) ($prevu->f ?? 0),
                            (int) ($prevu->jeunes ?? 0), (int) ($prevu->fpe ?? 0),
                            (int) ($prevu->cadres ?? 0),
                        ],
                        'backgroundColor' => '#94a3b8',
                    ],
                    [
                        'label' => 'Réalisé',
                        'data'  => [
                            (int) ($real->h ?? 0), (int) ($real->f ?? 0),
                            (int) ($real->jeunes ?? 0), (int) ($real->fpe ?? 0),
                            (int) ($real->cadres ?? 0),
                        ],
                        'backgroundColor' => '#3b82f6',
                    ],
                ],
                'labels' => ['Hommes', 'Femmes', 'Jeunes', 'FPE', 'Cadres'],
            ];
        });
    }

    protected function getType(): string { return 'bar'; }
}
