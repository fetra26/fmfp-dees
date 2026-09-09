<?php

namespace App\Filament\Resources\StatutsProjet\Pages;

use App\Filament\Resources\StatutsProjet\StatutProjetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStatutProjet extends EditRecord
{
    protected static string $resource = StatutProjetResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
