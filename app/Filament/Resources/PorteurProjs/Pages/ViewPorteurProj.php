<?php

namespace App\Filament\Resources\PorteurProjs\Pages;

use App\Filament\Resources\PorteurProjs\PorteurProjResource;
use App\Filament\Resources\PorteurProjs\Schemas\PorteurProjInfolist;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

/**
 * Page "Fiche projet 360°" — vue lecture organisée en 8 cartes colorées
 * reproduisant les 8 sections du fichier Excel DEES.
 */
class ViewPorteurProj extends ViewRecord
{
    protected static string $resource = PorteurProjResource::class;

    public function infolist(Schema $schema): Schema
    {
        return PorteurProjInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retour')
                ->label('← Retour à la liste')
                ->color('gray')
                ->url(PorteurProjResource::getUrl('index')),

            Action::make('exporter_pdf')
                ->label('📄 Exporter en PDF')
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn () => route('export.fiche-projet', ['porteurProj' => $this->record->id]))
                ->openUrlInNewTab(),

            EditAction::make()
                ->label('✏️ Modifier'),
        ];
    }

    public function getTitle(): string
    {
        $ref = $this->record?->projet?->reference ?? '—';
        $porteur = $this->record?->porteur?->raison_sociale ?? '';
        return "Fiche projet {$ref}" . ($porteur ? " — {$porteur}" : '');
    }
}
