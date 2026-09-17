<?php

namespace App\Filament\Widgets;

use App\Models\Region;
use App\Services\StatistiquesBeneficiaires;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bénéficiaires par secteur, en prévu et en réalisé.
 *
 * Le filtre région fait office de croisement : sans filtre le tableau donne la
 * répartition nationale par secteur, avec filtre il donne celle d'une seule
 * région. Plutôt qu'une grille de 11 secteurs × 23 régions dont la plupart des
 * cases seraient vides, on montre une coupe à la fois — lisible et exportable.
 *
 * ── Ce que ce tableau ne peut pas montrer ──
 *
 * « Jeunes » est un volume sans ventilation par sexe, et « Femmes cadres » ne
 * compte que des femmes : le modèle d'import ne collecte ni les jeunes femmes,
 * ni les hommes cadres. Ces croisements demanderaient d'étendre le fichier
 * Excel et de faire ressaisir la DEES.
 */
class BeneficiairesParSecteurWidget extends BaseWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'benef_par_secteur';

    protected static ?string $heading = '🏭 Bénéficiaires par secteur';
    protected static ?int $sort = 95;
    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = true;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => StatistiquesBeneficiaires::parSecteur($this->regionFiltree()))
            ->defaultSort('total_realise', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('libelle')
                    ->label('Secteur')
                    ->description(fn ($record) => $record->code)
                    ->wrap(),

                TextColumn::make('nb_projets')
                    ->label('Projets')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

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
                    ->alignEnd()
                    ->description(fn ($record) => $this->objectif($record->h_prevu, $record->h_realise)),

                TextColumn::make('f_realise')
                    ->label('Femmes')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->description(fn ($record) => $this->objectif($record->f_prevu, $record->f_realise)),

                TextColumn::make('jeunes_realise')
                    ->label('Jeunes')
                    ->tooltip('15-35 ans — non ventilé par sexe dans le fichier source')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->description(fn ($record) => $this->objectif($record->jeunes_prevu, $record->jeunes_realise)),

                TextColumn::make('cadres_realise')
                    ->label('Femmes cadres')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->description(fn ($record) => $this->objectif($record->cadres_prevu, $record->cadres_realise)),

                TextColumn::make('fpe_realise')
                    ->label('FPE')
                    ->tooltip('Formation pré-emploi')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->description(fn ($record) => $this->objectif($record->fpe_prevu, $record->fpe_realise)),
            ])
            ->filters([
                SelectFilter::make('region')
                    ->label('Région')
                    ->options(fn () => Region::orderBy('libelle')->pluck('libelle', 'id'))
                    ->placeholder('Toutes les régions')
                    ->searchable(),
            ]);
    }

    /** Région actuellement sélectionnée dans le filtre, le cas échéant. */
    private function regionFiltree(): ?int
    {
        $valeur = $this->tableFilters['region']['value'] ?? null;

        return filled($valeur) ? (int) $valeur : null;
    }

    /**
     * Rappel de l'objectif sous le chiffre réalisé.
     *
     * Sans objectif, on n'affiche aucun taux : 0 % laisserait croire à un
     * échec là où rien n'était prévu.
     */
    private function objectif(mixed $prevu, mixed $realise): ?string
    {
        $prevu = (int) $prevu;

        if ($prevu === 0) {
            return null;
        }

        $taux = StatistiquesBeneficiaires::tauxAtteinte($prevu, (int) $realise);

        return 'prévu ' . number_format($prevu, 0, ',', ' ') . ' · ' . $taux . ' %';
    }
}
