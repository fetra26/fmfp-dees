<?php

namespace App\Filament\Resources\Utilisateurs\Pages;

use App\Filament\Resources\Utilisateurs\UtilisateurResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUtilisateur extends EditRecord
{
    protected static string $resource = UtilisateurResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
