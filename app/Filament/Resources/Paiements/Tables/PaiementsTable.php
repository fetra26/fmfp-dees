<?php

namespace App\Filament\Resources\Paiements\Tables;

use App\Filament\Resources\Paiements\PaiementResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PaiementsTable
{
    public static function configure(Table $table): Table
    {
        $peutEditer = PaiementResource::peutEditer();

        return $table
            ->columns([
                TextColumn::make('porteurProj.projet.reference')
                    ->label('Réf. projet')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('porteurProj.porteur.raison_sociale')
                    ->label('Porteur')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('ligne')
                    ->label('Tranche')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'J1' => 'primary',
                        'J2' => 'warning',
                        'J3' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('date_paiement')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('montant')
                    ->label('Montant')
                    ->money('MGA', locale: 'fr')
                    ->alignRight()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('reference_ordre')
                    ->label('Réf. ordre')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('is_annule')
                    ->label('Annulé')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->sortable(),

                TextColumn::make('creePar.name')
                    ->label('Créé par')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Enregistré le')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('ligne')
                    ->label('Tranche')
                    ->options(['J1' => 'J1', 'J2' => 'J2', 'J3' => 'J3']),

                SelectFilter::make('porteur_proj_id')
                    ->label('Projet')
                    ->relationship('porteurProj.projet', 'reference')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_annule')
                    ->label('État')
                    ->placeholder('Tous')
                    ->trueLabel('Annulés seulement')
                    ->falseLabel('Actifs seulement'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible($peutEditer),
                DeleteAction::make()->visible($peutEditer),
            ])
            ->toolbarActions(
                $peutEditer
                    ? [BulkActionGroup::make([DeleteBulkAction::make()])]
                    : []
            )
            ->defaultSort('date_paiement', 'desc')
            ->emptyStateHeading('Aucun paiement')
            ->emptyStateDescription(
                $peutEditer
                    ? 'Cliquez sur "+ Nouveau paiement" pour saisir le premier paiement.'
                    : 'Les paiements enregistrés par l\'équipe DAF apparaîtront ici.'
            );
    }
}
