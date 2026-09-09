<?php

namespace App\Filament\Resources\Secteurs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SecteursTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('libelle')
                    ->label('Libellé')
                    ->wrap()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('projets_count')
                    ->label('Projets')
                    ->counts('projets')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
            ])
            ->defaultSort('code')
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
