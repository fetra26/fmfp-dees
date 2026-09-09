<?php

namespace App\Filament\Resources\RapportTechniques\Pages;

use App\Filament\Resources\RapportTechniques\RapportTechniqueResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRapportTechnique extends EditRecord
{
    protected static string $resource = RapportTechniqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
