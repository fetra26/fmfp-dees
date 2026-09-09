<?php

namespace App\Filament\Resources\Secteurs\Pages;

use App\Filament\Resources\Secteurs\SecteurResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSecteur extends EditRecord
{
    protected static string $resource = SecteurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
