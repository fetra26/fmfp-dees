<?php

namespace App\Filament\Widgets;

use App\Services\StatistiquesBeneficiaires;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Classement des régions par volume d'activité.
 *
 * Une ligne de bénéficiaires porte sa propre région quand la formation s'est
 * tenue hors de la région principale du projet ; c'est elle qui prime, la
 * région du projet ne servant que de repli. Un même projet peut donc
 * alimenter plusieurs régions, ce qui est le comportement voulu pour les
 * projets multi-lieux.
 */
class TopRegionsWidget extends BaseWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'top_regions';

    protected static ?string $heading = '🗺️ Classement des régions';
    protected static ?int $sort = 85;
    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = true;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => StatistiquesBeneficiaires::parRegion())
            ->defaultSort('nb_projets', 'desc')
            ->paginated([10, 25, 'all'])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('libelle')
                    ->label('Région')
                    ->description(fn ($record) => $record->code)
                    ->wrap(),

                TextColumn::make('nb_projets')
                    ->label('Projets')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),

                TextColumn::make('total_realise')
                    ->label('Bénéficiaires')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->description(fn ($record) => $this->objectif($record->total_prevu, $record->total_realise)),

                TextColumn::make('h_realise')
                    ->label('Hommes')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('f_realise')
                    ->label('Femmes')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('jeunes_realise')
                    ->label('Jeunes')
                    ->tooltip('15-35 ans — non ventilé par sexe dans le fichier source')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('cadres_realise')
                    ->label('Femmes cadres')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),
            ]);
    }

    private function objectif(mixed $prevu, mixed $realise): ?string
    {
        $prevu = (int) $prevu;

        if ($prevu === 0) {
            return null;
        }

        return 'prévu ' . number_format($prevu, 0, ',', ' ')
            . ' · ' . StatistiquesBeneficiaires::tauxAtteinte($prevu, (int) $realise) . ' %';
    }
}
