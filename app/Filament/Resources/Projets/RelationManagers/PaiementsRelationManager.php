<?php

namespace App\Filament\Resources\Projets\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaiementsRelationManager extends RelationManager
{
    protected static string $relationship = 'paiements';
    protected static ?string $title = 'Paiements';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('ligne')->options(['J1' => 'J1', 'J2' => 'J2', 'J3' => 'J3'])->required(),
            DatePicker::make('date_paiement')->label('Date paiement')->required(),
            TextInput::make('montant')->numeric()->required()->suffix('Ar'),
            TextInput::make('reference_ordre')->label('Référence ordre')->maxLength(100),
            Toggle::make('is_annule')->label('Annulé')->inline(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date_paiement')
            ->columns([
                TextColumn::make('ligne')->badge(),
                TextColumn::make('date_paiement')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('montant')->numeric()->suffix(' Ar')->sortable(),
                TextColumn::make('reference_ordre')->label('Référence'),
                IconColumn::make('is_annule')->label('Annulé')->boolean(),
            ])
            ->defaultSort('date_paiement')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
