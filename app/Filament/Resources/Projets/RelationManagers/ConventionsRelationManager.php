<?php

namespace App\Filament\Resources\Projets\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConventionsRelationManager extends RelationManager
{
    protected static string $relationship = 'conventions';
    protected static ?string $title = 'Conventions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('numero')->required()->maxLength(50)->unique(ignoreRecord: true),
                DatePicker::make('date_signature')->label('Date signature'),
                DatePicker::make('date_effet')->label("Date d'effet"),
                DatePicker::make('date_expiration')->label('Date expiration'),
            ]),
            Section::make('Montants (Ariary)')->columns(3)->schema([
                TextInput::make('montant_j1')->label('J1')->numeric()->default(0),
                TextInput::make('montant_j2')->label('J2')->numeric()->default(0),
                TextInput::make('montant_j3')->label('J3')->numeric()->default(0),
                TextInput::make('fonds_additionnel')->label('Fonds additionnel')->numeric()->default(0),
                TextInput::make('fonds_mutualise')->label('Fonds mutualisé')->numeric()->default(0),
                TextInput::make('montant_total')->label('Total')->numeric()->default(0),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero')
            ->columns([
                TextColumn::make('numero')->searchable(),
                TextColumn::make('date_signature')->label('Signature')->date('d/m/Y'),
                TextColumn::make('date_expiration')->label('Expiration')->date('d/m/Y'),
                TextColumn::make('montant_total')->label('Total')->numeric()->suffix(' Ar'),
                TextColumn::make('montant_j1')->label('J1')->numeric()->suffix(' Ar'),
                TextColumn::make('montant_j2')->label('J2')->numeric()->suffix(' Ar'),
                TextColumn::make('montant_j3')->label('J3')->numeric()->suffix(' Ar'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
