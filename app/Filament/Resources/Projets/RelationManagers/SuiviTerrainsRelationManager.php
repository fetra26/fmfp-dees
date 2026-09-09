<?php

namespace App\Filament\Resources\Projets\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuiviTerrainsRelationManager extends RelationManager
{
    protected static string $relationship = 'suiviTerrains';
    protected static ?string $title = 'Suivi terrain';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date_visite')->label('Date visite')->required(),
            TextInput::make('lieu')->maxLength(200),
            TextInput::make('taux_execution_observe')->label("Taux d'exécution (%)") ->numeric()->suffix('%'),
            Textarea::make('constats')->rows(3)->columnSpanFull(),
            Textarea::make('recommandations')->rows(3)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date_visite')
            ->columns([
                TextColumn::make('date_visite')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('lieu'),
                TextColumn::make('taux_execution_observe')->label('Taux obs.')->suffix('%'),
                TextColumn::make('constats')->limit(50),
            ])
            ->defaultSort('date_visite', 'desc')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
