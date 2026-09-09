<?php

namespace App\Filament\Resources\Entreprises\Tables;

use App\Models\Partenaire;
use App\Models\Porteur;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EntreprisesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('raison_sociale')->label('Raison sociale')->sortable()->searchable()->limit(40),
                TextColumn::make('sigle')->sortable()->searchable()->toggleable(),
                TextColumn::make('nif')->searchable()->toggleable(),
                TextColumn::make('cnaps')->label('CNaPS')->searchable()->toggleable(),
                TextColumn::make('secteur.libelle')->label('Secteur')->sortable()->toggleable(),
                TextColumn::make('region.libelle')->label('Région')->sortable(),
                TextColumn::make('ville')->sortable()->toggleable(),

                // Compteur projets en tant que porteur
                TextColumn::make('nb_projets_porteur')
                    ->label('Projets (porteur)')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function (Porteur $record) {
                        return $record->porteurProjs()->count();
                    })
                    ->sortable(query: fn (Builder $q, string $dir) =>
                        $q->withCount('porteurProjs')->orderBy('porteur_projs_count', $dir)
                    ),

                // Compteur projets en tant que partenaire
                TextColumn::make('nb_projets_partenaire')
                    ->label('Projets (partenaire)')
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(function (Porteur $record) {
                        return Partenaire::where('nom', 'LIKE', '%' . $record->raison_sociale . '%')
                            ->distinct('porteur_proj_id')
                            ->count('porteur_proj_id');
                    }),

                // Total des deux rôles
                TextColumn::make('nb_projets_total')
                    ->label('Total projets')
                    ->badge()
                    ->color('success')
                    ->getStateUsing(function (Porteur $record) {
                        $porteur = $record->porteurProjs()->count();
                        $partenaire = Partenaire::where('nom', 'LIKE', '%' . $record->raison_sociale . '%')
                            ->distinct('porteur_proj_id')->count('porteur_proj_id');
                        return $porteur + $partenaire;
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('secteur_id')->label('Secteur')->relationship('secteur', 'libelle'),
                SelectFilter::make('region_id')->label('Région')->relationship('region', 'libelle'),
                TrashedFilter::make(),
            ])
            ->defaultSort('raison_sociale')
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
