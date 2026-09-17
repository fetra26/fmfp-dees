<?php

namespace App\Filament\Pages;

use App\Imports\ProjetExcelImport;
use App\Models\Guichet;
use App\Models\ImportMapping;
use App\Models\Region;
use App\Models\Secteur;
use App\Models\StatutProjet;
use App\Models\Vague;
use App\Services\PreflightScanner;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Page "Assistant d'import" — Wizard interactif pour l'import Excel.
 *
 * Flow :
 *   1. Upload fichier
 *   2. Scan (PreflightScanner) → détecte les référentiels inconnus
 *   3. Résolution interactive par référentiel (mapping, création, ignore)
 *   4. Récapitulatif + lancement de l'import réel
 */
class ImportWizard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowUpTray;
    protected static ?string $title = 'Assistant d\'import';
    protected static ?string $navigationLabel = 'Assistant d\'import';
    protected static string | \UnitEnum | null $navigationGroup = 'Suivi des projets';
    protected static ?int $navigationSort = 3;
    protected string $view = 'filament.pages.import-wizard';

    public string $etape = 'upload'; // upload | scan | resolution | recap | import | done
    public ?string $cheminFichier = null;
    public array $scan = [];
    public array $resolutions = [];
    public int $refIndex = 0;
    public array $refKeys = ['secteur', 'vague', 'guichet', 'statut', 'region'];
    public array $rapportImport = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user?->isSuperAdmin() || $user?->hasAnyRole(['responsable_dees', 'equipe_dees', 'project_manager']) || false;
    }

    public function mount(): void
    {
        $this->etape = 'upload';
        $this->cheminFichier = null;
        $this->scan = [];
        $this->resolutions = [];
        $this->refIndex = 0;
        $this->rapportImport = [];
    }

    /** Action Filament : sélectionner + analyser un fichier */
    public function analyserAction(): Action
    {
        return Action::make('analyser')
            ->label('📤 Sélectionner un fichier à analyser')
            ->color('primary')
            ->form([
                FileUpload::make('fichier')
                    ->label('Fichier Excel (.xlsx)')
                    ->required()
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->disk('local')
                    ->directory('imports-temp')
                    ->preserveFilenames(),
            ])
            ->action(function (array $data): void {
                $relatif = is_array($data['fichier']) ? reset($data['fichier']) : $data['fichier'];
                $chemin = storage_path('app/private/' . ltrim((string) $relatif, '/'));
                if (! file_exists($chemin)) {
                    $chemin = storage_path('app/' . ltrim((string) $relatif, '/'));
                }
                if (! file_exists($chemin)) {
                    Notification::make()->title('Fichier introuvable')->body($chemin)->danger()->send();
                    return;
                }

                $this->cheminFichier = $chemin;

                try {
                    $this->scan = (new PreflightScanner())->scanner($chemin);
                } catch (\Throwable $e) {
                    Notification::make()->title('Erreur d\'analyse')->body($e->getMessage())->danger()->send();
                    return;
                }

                if ($this->scan['total_inconnus'] === 0) {
                    $this->etape = 'recap';
                    Notification::make()->title('Aucun inconnu détecté')->body('Tous les référentiels sont reconnus, prêt pour l\'import.')->success()->send();
                    return;
                }

                $this->etape = 'resolution';
                $this->refIndex = 0;
                $this->allerAuProchainInconnu();

                Notification::make()
                    ->title('Analyse terminée')
                    ->body("{$this->scan['total_inconnus']} valeur(s) à résoudre avant l'import")
                    ->warning()
                    ->send();
            });
    }

    protected function getHeaderActions(): array
    {
        if ($this->etape === 'upload') {
            return [$this->analyserAction()];
        }
        if (in_array($this->etape, ['resolution', 'recap', 'done'], true)) {
            return [
                Action::make('recommencer')
                    ->label('🔄 Recommencer')
                    ->color('gray')
                    ->action(fn () => $this->mount()),
            ];
        }
        return [];
    }

    /** Passe au prochain référentiel qui a des inconnus */
    protected function allerAuProchainInconnu(): void
    {
        while ($this->refIndex < count($this->refKeys)) {
            $ref = $this->refKeys[$this->refIndex];
            $items = $this->scan[$ref] ?? [];
            $inconnus = array_filter($items, fn ($it) => ($it['statut'] ?? 'connu') === 'inconnu');
            if (! empty($inconnus)) {
                // Initialiser les résolutions par défaut
                foreach ($inconnus as $val => $item) {
                    $this->resolutions[$ref][$val] ??= [
                        'action'       => 'create',  // par défaut : créer nouveau
                        'target_id'    => $item['suggestions'][0]['id'] ?? null,
                        // Utilise le libellé au format SEER (UPPER_SNAKE_CASE)
                        'target_label' => $item['libelle_seer'] ?? \App\Models\ImportMapping::formaterLibelle($val) ?? $val,
                        'memoriser'    => true,
                    ];
                    // Si suggestion forte (>80%), pré-sélectionner "map"
                    if (isset($item['suggestions'][0]) && $item['suggestions'][0]['similarite'] >= 80) {
                        $this->resolutions[$ref][$val]['action'] = 'map';
                    }
                }
                return;
            }
            $this->refIndex++;
        }

        // Aucun référentiel avec inconnu → récapitulatif
        $this->etape = 'recap';
    }

    /** Valide la résolution du référentiel actuel et passe au suivant */
    public function validerResolutionActuelle(): void
    {
        $this->refIndex++;
        $this->allerAuProchainInconnu();
    }

    /** Retour au référentiel précédent */
    public function retourResolutionPrecedente(): void
    {
        if ($this->refIndex > 0) {
            $this->refIndex--;
        } else {
            $this->etape = 'upload';
        }
    }

    /** Récapitulatif → lance l'import réel */
    public function lancerImportReel(): void
    {
        if (! $this->cheminFichier || ! file_exists($this->cheminFichier)) {
            Notification::make()->title('Fichier expiré')->body('Veuillez re-uploader le fichier.')->danger()->send();
            $this->etape = 'upload';
            return;
        }

        ini_set('memory_limit', '512M');
        set_time_limit(600);

        // 1) Appliquer les résolutions : créer les nouveaux référentiels + mémoriser
        $this->appliquerResolutions();

        // 2) Lancer l'import
        $import = new ProjetExcelImport();
        // Structure du template : L1 = titre section, L2 = en-têtes, L3+ = données
        $import->setFormat(2, 3, 'idee');
        $import->fichierEnCours = basename($this->cheminFichier);

        try {
            \Maatwebsite\Excel\Facades\Excel::import($import, $this->cheminFichier);
            $import->importerFeuillePartenaires($this->cheminFichier);
        } catch (\Throwable $e) {
            Notification::make()->title('Erreur d\'import')->body($e->getMessage())->danger()->send();
            return;
        }

        // Compter les conflits générés durant CET import (par fichier)
        $nbConflits = \App\Models\ImportConflict::where('fichier', basename($this->cheminFichier))
            ->where('statut', \App\Models\ImportConflict::STATUT_EN_ATTENTE)
            ->count();

        // « Lignes ignorées » confondait deux choses très différentes : un bas de
        // tableau vide, et une ligne qui a levé une exception. On les sépare, et
        // on donne les numéros de ligne avec leur motif — sans quoi le chiffre
        // n'apprend rien et oblige à fouiller le fichier à la main.
        $this->rapportImport = [
            'projets'            => $import->imported,
            'ignores'            => count($import->lignesIgnorees),
            'en_erreur'          => count($import->lignesEnErreur),
            'partenaires'        => $import->partenairesImportes,
            'partenaires_rejets' => count($import->partenairesRejets),
            'doublons'           => count($import->doublonsProjets),
            'erreurs'            => count($import->errors),
            'conflits'           => $nbConflits,
            'detail_ignorees'    => $import->lignesIgnorees,
            'detail_erreurs'     => $import->lignesEnErreur,
            'messages'           => array_slice($import->errors, 0, 50),
        ];

        $this->etape = 'done';
    }

    /** Applique les résolutions : crée les nouveaux + enregistre les mappings mémorisés */
    protected function appliquerResolutions(): void
    {
        $modelMap = [
            'secteur' => Secteur::class,
            'vague'   => Vague::class,
            'guichet' => Guichet::class,
            'region'  => Region::class,
            'statut'  => StatutProjet::class,
        ];

        $scanner = new PreflightScanner();

        foreach ($this->resolutions as $ref => $decisions) {
            $modelClass = $modelMap[$ref] ?? null;
            if (! $modelClass) continue;

            foreach ($decisions as $valeurSaisie => $decision) {
                $action = $decision['action'] ?? 'create';

                // 1) Si action = create → créer le nouveau référentiel avec libellé SEER (UPPER_SNAKE_CASE)
                if ($action === 'create') {
                    // Les nomenclatures officielles — 11 secteurs FMFP, 23 régions —
                    // restent fermées À L'IMPORT AUTOMATIQUE : resoudreSecteur() et
                    // resoudreRegion() ne créent jamais rien, ce qui évite les 54
                    // secteurs inventés à chaque graphie.
                    //
                    // ICI, c'est différent : un humain a délibérément choisi « Créer ».
                    // Le cas se présente vraiment — « Vatovavy Fitovinany » désigne la
                    // région d'avant la scission de 2021, indispensable pour reprendre
                    // les données historiques. On crée donc, en signalant que la valeur
                    // sort de la nomenclature pour qu'elle ne passe pas inaperçue.
                    $nomenclaturesOfficielles = ['region', 'secteur'];

                    $labelSeer = \App\Models\ImportMapping::formaterLibelle($decision['target_label'] ?? $valeurSaisie)
                        ?? $decision['target_label']
                        ?? $valeurSaisie;
                    // Libellé référentiel : varchar(150) → truncate impératif
                    $labelSeer = \Illuminate\Support\Str::limit($labelSeer, 150, '');
                    // Les cinq tables de référentiel portent un code NOT NULL, sans
                    // valeur par défaut et UNIQUE. Seul « statut » en recevait un :
                    // créer une vague ou un guichet échouait donc sur
                    // « Field 'code' doesn't have a default value ». On le génère
                    // désormais pour tous, en respectant la longueur de colonne.
                    $created = $modelClass::firstOrCreate(
                        ['libelle' => $labelSeer],
                        ['code' => $this->genererCodeReferentiel($modelClass, $labelSeer, $ref === 'statut' ? 30 : 20)]
                    );
                    if (in_array($ref, $nomenclaturesOfficielles, true) && $created->wasRecentlyCreated) {
                        Notification::make()
                            ->title(\Illuminate\Support\Str::ucfirst($ref) . ' créé hors nomenclature')
                            ->body("« {$labelSeer} » ne figure pas dans la liste officielle. À vérifier par la DEES, qui pourra le rattacher ou le renommer depuis les Référentiels.")
                            ->warning()
                            ->send();
                    }

                    $decision['target_id']    = $created->id;
                    $decision['target_label'] = $labelSeer;
                }

                // 2) Mémoriser si demandé
                if (! empty($decision['memoriser'])) {
                    $scanner->memoriser(
                        $ref,
                        $valeurSaisie,
                        $action,
                        $decision['target_id'] ?? null,
                        $decision['target_label'] ?? $valeurSaisie
                    );
                }
            }
        }
    }

    /**
     * Code unique dérivé du libellé d'un référentiel.
     *
     * La colonne code est NOT NULL, UNIQUE, et courte : 20 caractères pour
     * secteur, vague et guichet, 30 pour les statuts. Deux libellés distincts
     * pouvant produire le même code une fois tronqués, on suffixe jusqu'à
     * trouver une place libre plutôt que de laisser MySQL rejeter l'insertion.
     */
    private function genererCodeReferentiel(string $modelClass, string $libelle, int $longueurMax): string
    {
        $base = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($libelle, '_'));
        $base = \Illuminate\Support\Str::limit($base, $longueurMax, '') ?: 'REF';

        $code = $base;
        $suffixe = 1;

        while ($modelClass::where('code', $code)->exists()) {
            $suffixe++;
            $marque = '_' . $suffixe;
            $code = \Illuminate\Support\Str::limit($base, $longueurMax - strlen($marque), '') . $marque;
        }

        return $code;
    }

    public function recommencer(): void
    {
        $this->mount();
    }

    // Helpers pour la vue
    public function getRefActuelProperty(): ?string
    {
        return $this->refKeys[$this->refIndex] ?? null;
    }

    public function getRefLabelProperty(): string
    {
        return match ($this->refActuel) {
            'secteur' => '🏭 Secteurs',
            'vague'   => '🌊 Vagues',
            'guichet' => '🏢 Guichets',
            'statut'  => '📊 Statuts projet',
            'region'  => '🗺️ Régions',
            default   => '',
        };
    }

    public function getInconnusActuelsProperty(): array
    {
        $ref = $this->refActuel;
        if (! $ref) return [];
        $items = $this->scan[$ref] ?? [];
        return array_filter($items, fn ($it) => ($it['statut'] ?? 'connu') === 'inconnu');
    }

    public function getExistantsActuelsProperty(): array
    {
        $ref = $this->refActuel;
        return match ($ref) {
            'secteur' => Secteur::orderBy('libelle')->pluck('libelle', 'id')->toArray(),
            'vague'   => Vague::orderBy('libelle')->pluck('libelle', 'id')->toArray(),
            'guichet' => Guichet::orderBy('libelle')->pluck('libelle', 'id')->toArray(),
            'region'  => Region::orderBy('libelle')->pluck('libelle', 'id')->toArray(),
            'statut'  => StatutProjet::orderBy('libelle')->pluck('libelle', 'id')->toArray(),
            default   => [],
        };
    }
}
