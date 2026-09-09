<?php

namespace App\Filament\Resources\SuiviTerrains\Pages;

use App\Filament\Resources\SuiviTerrains\SuiviTerrainResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuiviTerrains extends ListRecords
{
    protected static string $resource = SuiviTerrainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
