<?php

namespace App\Filament\Resources\Conventions\Pages;

use App\Filament\Resources\Conventions\ConventionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditConvention extends EditRecord
{
    protected static string $resource = ConventionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
