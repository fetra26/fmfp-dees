<?php

namespace App\Filament\Resources\PorteurProjs\Pages;

use App\Filament\Resources\PorteurProjs\PorteurProjResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPorteurProj extends EditRecord
{
    protected static string $resource = PorteurProjResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Cache le bouton "Enregistrer" si le projet est verrouillé.
     * Le Super Admin garde son bouton pour pouvoir modifier.
     */
    protected function getSaveFormAction(): Action
    {
        $action = parent::getSaveFormAction();

        if ($this->record->estVerrouille() && ! auth()->user()?->isSuperAdmin()) {
            $action->hidden();
        }

        return $action;
    }

    /** Cache aussi "Enregistrer et fermer" */
    protected function getCancelFormAction(): Action
    {
        $action = parent::getCancelFormAction();

        if ($this->record->estVerrouille() && ! auth()->user()?->isSuperAdmin()) {
            $action->label('Retour');
        }

        return $action;
    }
}
