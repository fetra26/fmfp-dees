<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;

    public function infolist(Schema $schema): Schema
    {
        return AuditLogResource::infolist($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retour')
                ->label('← Retour au journal')
                ->color('gray')
                ->url(AuditLogResource::getUrl('index')),
        ];
    }

    public function getTitle(): string
    {
        return "Événement #{$this->record->id}";
    }
}
