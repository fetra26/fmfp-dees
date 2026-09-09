<?php

namespace App\Filament\Resources\Danos\Pages;

use App\Filament\Resources\Danos\DanoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDanos extends ListRecords
{
    protected static string $resource = DanoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
