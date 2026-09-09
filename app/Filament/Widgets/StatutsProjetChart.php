<?php

namespace App\Filament\Widgets;

use App\Models\PorteurProj;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class StatutsProjetChart extends ChartWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'statuts_projet';

    protected ?string $heading = 'Projets par statut';
    protected static ?int $sort = 2;
    protected static bool $isLazy = true;

    protected function getData(): array
    {
        return Cache::remember('widget.chart.statuts', 300, function () {
            $data = PorteurProj::selectRaw('statut_validation as statut, COUNT(*) as total')
                ->groupBy('statut_validation')
                ->orderByDesc('total')
                ->get();

            $colorMap = [
                'valide'     => '#10b981',  'en_cours'   => '#3b82f6',
                'en_attente' => '#f59e0b',  'incomplet'  => '#fbbf24',
                'suspendu'   => '#f97316',  'refuse'     => '#ef4444',
                'cloture'    => '#6b7280',  'annule'     => '#9ca3af',
                'resilie'    => '#7f1d1d',  'inelig'     => '#d97706',
            ];

            $labelMap = [
                'valide' => 'Validé', 'en_cours' => 'En cours',
                'en_attente' => 'En attente', 'incomplet' => 'Incomplet',
                'suspendu' => 'Suspendu', 'refuse' => 'Refusé',
                'cloture' => 'Clôturé', 'annule' => 'Annulé',
                'resilie' => 'Résilié', 'inelig' => 'Inéligible',
            ];

            return [
                'datasets' => [[
                    'data'            => $data->pluck('total')->toArray(),
                    'backgroundColor' => $data->map(fn ($r) => $colorMap[$r->statut] ?? '#94a3b8')->toArray(),
                ]],
                'labels' => $data->map(fn ($r) => $labelMap[$r->statut] ?? ucfirst(str_replace('_', ' ', $r->statut)))->toArray(),
            ];
        });
    }

    protected function getType(): string { return 'pie'; }
}
