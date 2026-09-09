<?php

namespace App\Filament\Resources\Vagues\Pages;

use App\Filament\Resources\Vagues\VagueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVagues extends ListRecords
{
    protected static string $resource = VagueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
