<?php

namespace App\Filament\Resources\Guichets\Pages;

use App\Filament\Resources\Guichets\GuichetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGuichet extends EditRecord
{
    protected static string $resource = GuichetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
