<?php

namespace App\Filament\Resources\Reserves\Pages;

use App\Filament\Resources\Reserves\ReserveResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditReserve extends EditRecord
{
    protected static string $resource = ReserveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
