<?php

namespace App\Filament\Resources\Projets\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReservesRelationManager extends RelationManager
{
    protected static string $relationship = 'reserves';
    protected static ?string $title = 'Réserves';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('description')->required()->rows(3)->columnSpanFull(),
            Select::make('niveau')->options(['mineure' => 'Mineure', 'majeure' => 'Majeure', 'bloquante' => 'Bloquante'])->default('mineure'),
            Select::make('statut')->options(['ouverte' => 'Ouverte', 'en_cours' => 'En cours', 'levee' => 'Levée'])->default('ouverte'),
            DatePicker::make('echeance')->label('Échéance'),
            DatePicker::make('date_levee')->label('Date levée'),
            Textarea::make('reponse_porteur')->label('Réponse porteur')->rows(3)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('description')->limit(50),
                TextColumn::make('niveau')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'bloquante' => 'danger', 'majeure' => 'warning', default => 'gray',
                    }),
                TextColumn::make('statut')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'levee' => 'success', 'en_cours' => 'warning', default => 'danger',
                    }),
                TextColumn::make('echeance')->date('d/m/Y'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
