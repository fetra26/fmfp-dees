<?php

namespace App\Filament\Resources\Projets\RelationManagers;

use App\Models\TypeRelance;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RelancesRelationManager extends RelationManager
{
    protected static string $relationship = 'relances';
    protected static ?string $title = 'Relances';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type_relance_id')->label('Type')->options(TypeRelance::pluck('libelle', 'id'))->required(),
            DatePicker::make('date_relance')->label('Date relance')->required(),
            Textarea::make('contenu')->rows(3)->columnSpanFull(),
            Toggle::make('is_repondu')->label('Répondu')->inline(false),
            DatePicker::make('date_reponse')->label('Date réponse'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date_relance')
            ->columns([
                TextColumn::make('type.libelle')->label('Type'),
                TextColumn::make('date_relance')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('contenu')->limit(40),
                IconColumn::make('is_repondu')->label('Répondu')->boolean(),
                TextColumn::make('date_reponse')->label('Réponse le')->date('d/m/Y'),
            ])
            ->defaultSort('date_relance', 'desc')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
