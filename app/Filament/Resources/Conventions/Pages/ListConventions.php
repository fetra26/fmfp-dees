<?php

namespace App\Filament\Resources\Conventions\Pages;

use App\Filament\Resources\Conventions\ConventionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConventions extends ListRecords
{
    protected static string $resource = ConventionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
