<?php

namespace App\Filament\Resources\Utilisateurs\Pages;

use App\Filament\Resources\Utilisateurs\UtilisateurResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUtilisateur extends CreateRecord
{
    protected static string $resource = UtilisateurResource::class;
}
