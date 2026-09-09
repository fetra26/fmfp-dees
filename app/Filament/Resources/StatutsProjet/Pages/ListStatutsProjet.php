<?php

namespace App\Filament\Resources\StatutsProjet\Pages;

use App\Filament\Resources\StatutsProjet\StatutProjetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStatutsProjet extends ListRecords
{
    protected static string $resource = StatutProjetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('+ Nouveau statut')];
    }
}
