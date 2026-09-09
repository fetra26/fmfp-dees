<?php

namespace App\Filament\Resources\SuiviTerrains\Pages;

use App\Filament\Resources\SuiviTerrains\SuiviTerrainResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSuiviTerrain extends EditRecord
{
    protected static string $resource = SuiviTerrainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
