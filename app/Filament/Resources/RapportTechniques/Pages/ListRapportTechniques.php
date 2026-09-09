<?php

namespace App\Filament\Resources\RapportTechniques\Pages;

use App\Filament\Resources\RapportTechniques\RapportTechniqueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRapportTechniques extends ListRecords
{
    protected static string $resource = RapportTechniqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
