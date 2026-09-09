<?php

namespace App\Filament\Resources\Entreprises\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjetsRelationManager extends RelationManager
{
    protected static string $relationship = 'porteurProjs';
    protected static ?string $title = 'Projets (porteur)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference_convention')
            ->columns([
                TextColumn::make('projet.reference')->label('Référence')->searchable()->sortable(),
                TextColumn::make('projet.intitule')->label('Intitulé')->limit(40)->searchable(),
                TextColumn::make('statut_validation')->label('Statut')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'valide'   => 'success',
                        'refuse'   => 'danger',
                        'en_cours' => 'info',
                        default    => 'gray',
                    }),
                TextColumn::make('niveau_alerte')->label('Alerte')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'rouge'  => 'danger',
                        'orange' => 'warning',
                        default  => 'success',
                    }),
                TextColumn::make('montant_total')->label('Montant total')->numeric()->suffix(' Ar'),
                TextColumn::make('date_debut')->label('Début')->date('d/m/Y'),
                TextColumn::make('date_fin')->label('Fin')->date('d/m/Y'),
            ])
            ->filters([
                SelectFilter::make('statut_validation')->label('Statut')
                    ->options(['valide' => 'Validé', 'refuse' => 'Refusé', 'en_cours' => 'En cours', 'en_attente' => 'En attente']),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
