<?php

namespace App\Filament\Widgets;

use App\Models\Paiement;
use App\Models\PorteurProj;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class BilanFinancierWidget extends BaseWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'bilan_financier';

    protected static ?int $sort = 40;
    protected ?string $heading = "💰 Bilan financier";

    protected function getStats(): array
    {
        $data = Cache::remember('widget.bilan.financier', 300, function () {
            // Une seule requête pour les sommes des montants
            $sommesPP = PorteurProj::selectRaw('
                SUM(montant_total) as allocation,
                SUM(financement_demande) as demande,
                SUM(fonds_additionnel) as fonds_add,
                SUM(fonds_mutualise) as fonds_mut
            ')->first();

            // Une seule requête pour les paiements par tranche
            $sommesPaiements = Paiement::where('is_annule', false)
                ->selectRaw('
                    SUM(CASE WHEN ligne = "J1" THEN montant ELSE 0 END) as j1,
                    SUM(CASE WHEN ligne = "J2" THEN montant ELSE 0 END) as j2,
                    SUM(CASE WHEN ligne = "J3" THEN montant ELSE 0 END) as j3
                ')->first();

            return [
                'allocation'   => (int) ($sommesPP->allocation ?? 0),
                'fonds_add'    => (int) ($sommesPP->fonds_add ?? 0),
                'fonds_mut'    => (int) ($sommesPP->fonds_mut ?? 0),
                'j1'           => (int) ($sommesPaiements->j1 ?? 0),
                'j2'           => (int) ($sommesPaiements->j2 ?? 0),
                'j3'           => (int) ($sommesPaiements->j3 ?? 0),
            ];
        });

        $totalVerse = $data['j1'] + $data['j2'] + $data['j3'];
        $taux = $data['allocation'] > 0 ? round(($totalVerse / $data['allocation']) * 100, 1) : 0;

        return [
            Stat::make('Allocation totale', $this->formatAr($data['allocation']))
                ->description('Montant total engagé')
                ->icon('heroicon-o-currency-dollar')
                ->color('primary'),

            Stat::make('Total versé (J1+J2+J3)', $this->formatAr($totalVerse))
                ->description("Taux consommation : {$taux}%")
                ->descriptionIcon($taux >= 75 ? 'heroicon-m-check-circle' : 'heroicon-m-clock')
                ->color($taux >= 75 ? 'success' : ($taux >= 50 ? 'warning' : 'gray'))
                ->chart([0, $data['j1'], $data['j1'] + $data['j2'], $totalVerse]),

            Stat::make('Tranche J1', $this->formatAr($data['j1']))
                ->description('1ère tranche versée')
                ->icon('heroicon-o-banknotes')
                ->color('info'),

            Stat::make('Tranche J2 + J3', $this->formatAr($data['j2'] + $data['j3']))
                ->description('Tranches suivantes')
                ->icon('heroicon-o-banknotes')
                ->color('info'),

            Stat::make('Fonds additionnels', $this->formatAr($data['fonds_add']))
                ->description('Hors FMFP')
                ->icon('heroicon-o-plus-circle')
                ->color('gray'),

            Stat::make('Fonds mutualisés', $this->formatAr($data['fonds_mut']))
                ->description('Partagés')
                ->icon('heroicon-o-user-group')
                ->color('gray'),
        ];
    }

    public function getColumns(): int
    {
        return 3;
    }

    private function formatAr(int $montant): string
    {
        if ($montant >= 1_000_000_000) return round($montant / 1_000_000_000, 2) . ' Mds Ar';
        if ($montant >= 1_000_000)     return round($montant / 1_000_000, 1) . ' M Ar';
        if ($montant >= 1_000)         return round($montant / 1_000, 1) . ' k Ar';
        return number_format($montant, 0, ',', ' ') . ' Ar';
    }
}
