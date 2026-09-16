<?php

namespace App\Filament\Widgets;

use App\Models\PorteurProj;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class FinancementParGuichetChart extends ChartWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'financement_par_guichet';

    protected ?string $heading = "💼 Financement par guichet (Ar)";
    protected static ?int $sort = 100;
    protected static bool $isLazy = true;

    protected function getData(): array
    {
        return Cache::remember('widget.chart.financement_guichet', 300, function () {
            $data = PorteurProj::join('projet', 'porteur_proj.projet_id', '=', 'projet.id')
                ->join('guichet', 'projet.guichet_id', '=', 'guichet.id')
                ->selectRaw('guichet.code as guichet, SUM(porteur_proj.montant_total) as total')
                ->groupBy('guichet.code')
                ->orderByDesc('total')
                ->get();

            return [
                'datasets' => [[
                    'label'           => 'Montant total (Ar)',
                    'data'            => $data->pluck('total')->map(fn ($v) => (int) $v)->toArray(),
                    'backgroundColor' => '#3b82f6',
                ]],
                'labels' => $data->pluck('guichet')->toArray(),
            ];
        });
    }

    protected function getType(): string { return 'bar'; }
}
