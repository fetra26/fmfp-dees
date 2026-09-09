<?php

namespace App\Filament\Resources\Secteurs\Pages;

use App\Filament\Resources\Secteurs\SecteurResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSecteurs extends ListRecords
{
    protected static string $resource = SecteurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
