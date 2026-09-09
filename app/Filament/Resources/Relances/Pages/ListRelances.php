<?php

namespace App\Filament\Resources\Relances\Pages;

use App\Filament\Resources\Relances\RelanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRelances extends ListRecords
{
    protected static string $resource = RelanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
