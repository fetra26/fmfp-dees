<?php

namespace App\Filament\Pages;

use App\Models\PorteurProj;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Projets en retard sur leur date de fin de convention.
 *
 * Les niveaux sont calculés chaque nuit par ClassifyAlertsJob à partir du
 * nombre de jours écoulés depuis date_fin :
 *
 *   verte   [30-60[  première relance préventive
 *   orange  [60-90[  deuxième relance + mise en demeure
 *   rouge   90+      procédure de résiliation
 *
 * À noter : « verte » couvre aussi les projets dans les temps, le job
 * renvoyant cette valeur par défaut. Le filtre « en retard » permet donc
 * d'écarter ceux dont l'échéance n'est pas encore passée — sans quoi la liste
 * mêlerait des dossiers à relancer et des dossiers qui vont très bien.
 */
class ProjetsEnAlerte extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static \UnitEnum|string|null $navigationGroup = 'Alertes';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Projets en alerte';

    protected static ?string $navigationLabel = 'Projets en alerte';

    protected string $view = 'filament.pages.projets-en-alerte';

    /** Pastille de navigation : le nombre de dossiers réellement à traiter. */
    public static function getNavigationBadge(): ?string
    {
        $n = PorteurProj::query()
            ->whereIn('niveau_alerte', ['orange', 'rouge'])
            ->whereNotNull('date_fin')
            ->whereNull('date_resiliation')
            ->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $rouges = PorteurProj::query()
            ->where('niveau_alerte', 'rouge')
            ->whereNull('date_resiliation')
            ->count();

        return $rouges > 0 ? 'danger' : 'warning';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PorteurProj::query()
                    ->with(['projet', 'porteur'])
                    ->whereNotNull('date_fin')
            )
            ->defaultSort('date_fin', 'asc')
            ->columns([
                TextColumn::make('projet.reference')
                    ->label('Projet')
                    ->description(fn ($record) => $record->projet?->intitule)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('porteur.raison_sociale')
                    ->label('Porteur')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('date_fin')
                    ->label('Fin de convention')
                    ->date('d/m/Y')
                    ->sortable(),

                // Le chiffre qui fait agir : combien de jours de dépassement.
                TextColumn::make('retard')
                    ->label('Retard')
                    ->state(fn ($record) => $this->joursDeRetard($record->date_fin))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state >= 90    => 'danger',
                        $state >= 60    => 'warning',
                        $state >= 30    => 'success',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : $state . ' j'),

                TextColumn::make('niveau_alerte')
                    ->label('Niveau')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'rouge'  => 'danger',
                        'orange' => 'warning',
                        'verte'  => 'success',
                        default  => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst((string) $state)),

                TextColumn::make('date_relance_1')
                    ->label('1re relance')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('date_relance_2')
                    ->label('2e relance')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('date_mise_en_demeure')
                    ->label('Mise en demeure')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('date_resiliation')
                    ->label('Résiliation')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color('danger')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('niveau_alerte')
                    ->label('Niveau')
                    ->options([
                        'rouge'  => 'Rouge — résiliation (90 j et plus)',
                        'orange' => 'Orange — mise en demeure (60-89 j)',
                        'verte'  => 'Verte — relance préventive (30-59 j)',
                    ]),

                TernaryFilter::make('en_retard')
                    ->label('Échéance dépassée')
                    ->placeholder('Tous les projets')
                    ->trueLabel('Uniquement les retards')
                    ->falseLabel('Uniquement dans les temps')
                    ->queries(
                        true: fn (Builder $q) => $q->whereDate('date_fin', '<', now()),
                        false: fn (Builder $q) => $q->whereDate('date_fin', '>=', now()),
                        blank: fn (Builder $q) => $q,
                    )
                    ->default(true),

                TernaryFilter::make('resilies')
                    ->label('Dossiers résiliés')
                    ->placeholder('Tous')
                    ->trueLabel('Uniquement les résiliés')
                    ->falseLabel('Masquer les résiliés')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('date_resiliation'),
                        false: fn (Builder $q) => $q->whereNull('date_resiliation'),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->emptyStateHeading('Aucun projet en alerte')
            ->emptyStateDescription("Aucune convention n'a dépassé sa date de fin.");
    }

    /** Jours écoulés depuis l'échéance, null si elle n'est pas encore passée. */
    private function joursDeRetard(mixed $dateFin): ?int
    {
        if (blank($dateFin)) {
            return null;
        }

        $fin = Carbon::parse($dateFin)->startOfDay();
        $aujourdhui = Carbon::today();

        return $aujourdhui->lessThanOrEqualTo($fin) ? null : (int) $fin->diffInDays($aujourdhui);
    }
}
