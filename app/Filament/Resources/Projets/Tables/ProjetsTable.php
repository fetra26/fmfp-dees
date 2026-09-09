<?php

namespace App\Filament\Resources\Projets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProjetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->sortable()->searchable(),
                TextColumn::make('intitule')->sortable()->searchable()->limit(40),
                TextColumn::make('statut.libelle')->label('Statut')->sortable()->badge(),
                TextColumn::make('guichet.libelle')->label('Guichet')->sortable(),
                TextColumn::make('region.libelle')->label('Région')->sortable(),
                TextColumn::make('date_debut')->label('Début')->date('d/m/Y')->sortable(),
                TextColumn::make('date_fin')->label('Fin')->date('d/m/Y')->sortable(),
                TextColumn::make('porteurProjs_count')
                    ->label('Porteurs')
                    ->counts('porteurProjs'),
            ])
            ->filters([
                SelectFilter::make('statut_projet_id')->label('Statut')->relationship('statut', 'libelle'),
                SelectFilter::make('guichet_id')->label('Guichet')->relationship('guichet', 'libelle'),
                TrashedFilter::make(),
            ])
            ->defaultSort('reference', 'desc')
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
