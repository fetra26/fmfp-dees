<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = $data['guard_name'] ?? 'web';
        $data['name']       = mb_strtolower($data['name']);
        return $data;
    }

    protected function afterCreate(): void
    {
        // Cache Spatie à invalider pour que le nouveau rôle soit reconnu immédiatement
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
