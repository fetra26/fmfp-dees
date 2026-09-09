<?php

namespace App\Filament\Resources\Vagues\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VaguesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->sortable()->searchable(),
                TextColumn::make('libelle')->sortable()->searchable(),
                TextColumn::make('annee')->sortable(),
                TextColumn::make('guichet.libelle')->label('Guichet')->sortable(),
                IconColumn::make('is_active')->boolean()->label('Actif'),
            ])
            ->defaultSort('code')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
