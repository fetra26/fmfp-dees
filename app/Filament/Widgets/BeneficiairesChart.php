<?php

namespace App\Filament\Widgets;

use App\Models\Benef;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class BeneficiairesChart extends ChartWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'beneficiaires';

    protected ?string $heading = "👤 Bénéficiaires réels — Répartition";
    protected static ?int $sort = 110;
    protected static bool $isLazy = true;

    protected function getData(): array
    {
        return Cache::remember('widget.chart.beneficiaires', 300, function () {
            $totals = Benef::where('type', 'realise')
                ->selectRaw('SUM(h) as hommes, SUM(f) as femmes, SUM(jeunes) as jeunes, SUM(fpe) as fpe, SUM(cadres) as cadres')
                ->first();

            return [
                'datasets' => [[
                    'data' => [
                        (int) ($totals->hommes ?? 0),
                        (int) ($totals->femmes ?? 0),
                        (int) ($totals->jeunes ?? 0),
                        (int) ($totals->fpe    ?? 0),
                        (int) ($totals->cadres ?? 0),
                    ],
                    'backgroundColor' => ['#3b82f6','#ec4899','#f59e0b','#10b981','#8b5cf6'],
                ]],
                'labels' => ['Hommes', 'Femmes', 'Jeunes', 'FPE', 'Cadres'],
            ];
        });
    }

    protected function getType(): string { return 'doughnut'; }
}
