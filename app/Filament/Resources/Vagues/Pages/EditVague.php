<?php

namespace App\Filament\Resources\Vagues\Pages;

use App\Filament\Resources\Vagues\VagueResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVague extends EditRecord
{
    protected static string $resource = VagueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
