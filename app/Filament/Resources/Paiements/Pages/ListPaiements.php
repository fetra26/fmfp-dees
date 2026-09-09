<?php

namespace App\Filament\Resources\Paiements\Pages;

use App\Filament\Resources\Paiements\PaiementResource;
use App\Imports\PaiementsExcelImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPaiements extends ListRecords
{
    protected static string $resource = PaiementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ─── Télécharger le template Excel vide ───
            Action::make('telecharger_template')
                ->label('📄 Télécharger le template')
                ->color('gray')
                ->visible(fn () => PaiementResource::peutEditer())
                ->action(function () {
                    $chemin = base_path('TEMPLATE_PAIEMENTS_DAF.xlsx');
                    if (! file_exists($chemin)) {
                        \Artisan::call('template:generer-paiements');
                    }
                    return response()->download($chemin);
                }),

            // ─── Importer un fichier Excel de paiements ───
            Action::make('importer')
                ->label('⬆️ Importer paiements')
                ->color('success')
                ->visible(fn () => PaiementResource::peutEditer())
                ->form([
                    FileUpload::make('fichier')
                        ->label('Fichier Excel (.xlsx)')
                        ->required()
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->storeFiles(false)
                        ->helperText('Utilisez le template TEMPLATE_PAIEMENTS_DAF.xlsx généré par le bouton "Télécharger".'),
                ])
                ->action(function (array $data) {
                    ini_set('memory_limit', '512M');
                    set_time_limit(300);

                    $chemin = $data['fichier']->getRealPath();
                    $import = new PaiementsExcelImport();

                    try {
                        $import->importer($chemin);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Erreur d\'import')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }

                    // ─── Notification de synthèse ───
                    $lignes = [
                        sprintf('✅ %d paiement(s) créé(s)', $import->importes),
                    ];

                    if ($import->misAJour > 0) {
                        $lignes[] = sprintf('🔄 %d paiement(s) mis à jour (doublon détecté)', $import->misAJour);
                    }
                    if ($import->ignores > 0) {
                        $lignes[] = sprintf('⚠️ %d ligne(s) ignorée(s)', $import->ignores);
                    }
                    if (! empty($import->rejets)) {
                        $lignes[] = "Détails des rejets (extrait) :";
                        foreach (array_slice($import->rejets, 0, 5) as $r) {
                            $lignes[] = "   • {$r}";
                        }
                        if (count($import->rejets) > 5) {
                            $lignes[] = sprintf('   … et %d autre(s)', count($import->rejets) - 5);
                        }
                    }

                    $type = $import->ignores > 0 ? 'warning' : 'success';

                    Notification::make()
                        ->title('Import paiements terminé')
                        ->body(implode("\n", $lignes))
                        ->{$type}()
                        ->persistent()
                        ->send();
                }),

            CreateAction::make()
                ->label('+ Nouveau paiement')
                ->visible(fn () => PaiementResource::peutEditer()),
        ];
    }
}
