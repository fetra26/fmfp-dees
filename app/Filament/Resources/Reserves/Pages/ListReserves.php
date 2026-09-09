<?php

namespace App\Filament\Resources\Reserves\Pages;

use App\Filament\Resources\Reserves\ReserveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReserves extends ListRecords
{
    protected static string $resource = ReserveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
