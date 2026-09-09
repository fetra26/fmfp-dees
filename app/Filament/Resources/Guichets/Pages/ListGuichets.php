<?php

namespace App\Filament\Resources\Guichets\Pages;

use App\Filament\Resources\Guichets\GuichetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGuichets extends ListRecords
{
    protected static string $resource = GuichetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
