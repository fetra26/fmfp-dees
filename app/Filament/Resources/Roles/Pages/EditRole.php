<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool =>
                    ! in_array($this->record->name, RoleResource::ROLES_PROTEGES, true)
                )
                ->requiresConfirmation()
                ->modalHeading('Supprimer ce rôle ?')
                ->modalDescription(fn (): string => "Le rôle « {$this->record->name} » sera supprimé. Les {$this->record->users()->count()} utilisateur(s) associé(s) le perdront mais garderont leur compte.")
                ->before(function (): void {
                    /** @var Role $role */
                    $role = $this->record;
                    $role->users()->detach();
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Le champ name est disabled pour les rôles protégés → on ne le récupère pas du formulaire
        if (in_array($this->record->name, RoleResource::ROLES_PROTEGES, true)) {
            unset($data['name']);
        } elseif (isset($data['name'])) {
            $data['name'] = mb_strtolower($data['name']);
        }
        return $data;
    }

    protected function afterSave(): void
    {
        // Cache Spatie invalidé pour prise en compte immédiate des changements
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
