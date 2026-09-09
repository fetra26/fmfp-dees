<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Page Paramètres — Personnalisation par utilisateur.
 *
 * Permet à chaque utilisateur de configurer sa vue de la table Données Projets :
 *   - Colonnes visibles par défaut
 *   - Colonnes figées à gauche
 *   - Pagination et tri par défaut
 *
 * Les préférences sont sauvegardées dans users.preferences (JSON).
 *
 * @property-read Schema $form
 */
class Parametres extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;
    protected static ?string $title = 'Paramètres';
    protected static ?string $navigationLabel = 'Paramètres';
    protected static ?int $navigationSort = 100;
    protected string $view = 'filament.pages.parametres';

    public ?array $data = [];

    public function mount(): void
    {
        $user = Auth::user();
        $prefs = $user->preferences ?? User::defaultPreferences();

        $this->form->fill([
            'colonnes_visibles' => $prefs['colonnes_visibles'] ?? User::defaultPreferences()['colonnes_visibles'],
            'colonnes_figees'   => $prefs['colonnes_figees']   ?? User::defaultPreferences()['colonnes_figees'],
            'pagination_defaut' => $prefs['pagination_defaut'] ?? 25,
            'tri_colonne'       => $prefs['tri_colonne']       ?? 'created_at',
            'tri_sens'          => $prefs['tri_sens']          ?? 'desc',

            // ─── Config système (Super Admin uniquement) ───
            'projet_champs_obligatoires' => \App\Models\SystemSetting::get(
                'projet.champs_obligatoires',
                ['porteur_id', 'intitule', 'secteur_id', 'guichet_id']
            ),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('parametres')->columnSpanFull()->tabs([

                    // ═══════════ ONGLET 1 : COLONNES VISIBLES ═══════════
                    Tabs\Tab::make('Colonnes visibles')
                        ->icon('heroicon-o-eye')
                        ->schema([
                            Section::make('Colonnes affichées par défaut')
                                ->description('Sélectionnez les colonnes que vous voulez voir à l\'ouverture de la table Données Projets.')
                                ->schema([
                                    CheckboxList::make('colonnes_visibles')
                                        ->label('')
                                        ->columns(3)
                                        ->bulkToggleable()
                                        ->options(self::optionsColonnes()),
                                ]),
                        ]),

                    // ═══════════ ONGLET 2 : COLONNES FIGÉES ═══════════
                    Tabs\Tab::make('Colonnes figées')
                        ->icon('heroicon-o-lock-closed')
                        ->schema([
                            Section::make('Colonnes figées à gauche')
                                ->description('Ces colonnes resteront visibles à gauche pendant le scroll horizontal. Max recommandé : 5-6 colonnes.')
                                ->schema([
                                    CheckboxList::make('colonnes_figees')
                                        ->label('')
                                        ->columns(3)
                                        ->bulkToggleable()
                                        ->options(self::optionsColonnesFigeables()),
                                ]),
                        ]),

                    // ═══════════ ONGLET 3 : PAGINATION & TRI ═══════════
                    Tabs\Tab::make('Pagination & Tri')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema([
                            Section::make('Affichage par défaut')
                                ->columns(2)
                                ->schema([
                                    Select::make('pagination_defaut')
                                        ->label('Nombre de lignes par page')
                                        ->options([
                                            10 => '10 lignes',
                                            25 => '25 lignes',
                                            50 => '50 lignes',
                                            100 => '100 lignes',
                                        ])
                                        ->default(25)
                                        ->native(false),

                                    Select::make('tri_colonne')
                                        ->label('Trier par')
                                        ->options([
                                            'created_at'          => 'Date de création',
                                            'projet.reference'    => 'Référence projet',
                                            'porteur.raison_sociale' => 'Porteur',
                                            'statut_validation'   => 'Statut',
                                            'date_fin'            => 'Date de fin',
                                            'montant_total'       => 'Montant total',
                                        ])
                                        ->default('created_at')
                                        ->native(false),

                                    Select::make('tri_sens')
                                        ->label('Sens du tri')
                                        ->options([
                                            'asc'  => '↑ Croissant (A → Z / 0 → 9)',
                                            'desc' => '↓ Décroissant (Z → A / 9 → 0)',
                                        ])
                                        ->default('desc')
                                        ->native(false),
                                ]),
                        ]),

                    // ═══════════ ONGLET 4 : SYSTÈME (Super Admin uniquement) ═══════════
                    Tabs\Tab::make('Système')
                        ->icon('heroicon-o-cog-8-tooth')
                        ->badge('Admin')
                        ->badgeColor('danger')
                        ->visible(fn () => Auth::user()?->isSuperAdmin() ?? false)
                        ->schema([
                            Section::make('Champs obligatoires à la création d\'un projet')
                                ->description('Sélectionnez les champs que les utilisateurs doivent obligatoirement remplir pour créer un nouveau projet. Applique la règle à tous les utilisateurs.')
                                ->schema([
                                    CheckboxList::make('projet_champs_obligatoires')
                                        ->label('')
                                        ->columns(2)
                                        ->bulkToggleable()
                                        ->options([
                                            'porteur_id'    => 'Porteur (entreprise)',
                                            'intitule'      => 'Intitulé du projet',
                                            'reference'     => 'Référence projet',
                                            'reference_convention' => 'Référence convention',
                                            'secteur_id'    => 'Secteur',
                                            'vague_id'      => 'Vague',
                                            'guichet_id'    => 'Guichet',
                                            'region_id'     => 'Région',
                                            'date_debut'    => 'Date de début',
                                            'date_fin'      => 'Date de fin',
                                            'montant_total' => 'Montant total',
                                        ])
                                        ->descriptions([
                                            'porteur_id'    => 'L\'entreprise porteuse du projet',
                                            'intitule'      => 'Ex: "Formation soudure niveau 2"',
                                            'reference'     => 'Auto-générée si vide',
                                            'secteur_id'    => 'BTP, AGRO, TELECOM, etc.',
                                            'guichet_id'    => 'RIE, PIS, PII, EQUITE, etc.',
                                        ])
                                        ->helperText('Ces champs bloqueront la sauvegarde s\'ils ne sont pas remplis.'),
                                ]),
                        ]),
                ]),
            ])
            ->statePath('data');
    }

    public function enregistrer(): void
    {
        // Récupérer les données du formulaire
        $data = $this->form->getState();

        // ─── Sauvegarder la config système si Super Admin ───
        if (Auth::user()?->isSuperAdmin() && isset($data['projet_champs_obligatoires'])) {
            \App\Models\SystemSetting::set(
                'projet.champs_obligatoires',
                array_values($data['projet_champs_obligatoires'])
            );
            unset($data['projet_champs_obligatoires']);  // ne pas mettre dans user.preferences
        }

        // Log pour debug
        \Illuminate\Support\Facades\Log::info('Paramètres - enregistrer() appelé', [
            'user_id'       => Auth::id(),
            'form_state'    => $data,
            'data_property' => $this->data,
        ]);

        // Fallback : si getState() retourne vide, utiliser directement $this->data
        if (empty($data) && ! empty($this->data)) {
            $data = $this->data;
        }

        $user = Auth::user();
        $user->preferences = $data;
        $user->save();

        // Invalider la session Filament pour que les nouvelles préférences prennent effet
        $this->viderSessionColonnes();

        Notification::make()
            ->title('✔ Paramètres enregistrés')
            ->body(sprintf(
                'Colonnes visibles : %d — Colonnes figées : %d. Retournez sur Données Projets pour voir.',
                count($data['colonnes_visibles'] ?? []),
                count($data['colonnes_figees'] ?? [])
            ))
            ->success()
            ->duration(6000)
            ->send();
    }

    /**
     * Vide la session Filament des colonnes mémorisées pour la table PorteurProj.
     * Force ainsi la relecture des préférences utilisateur au prochain accès.
     */
    private function viderSessionColonnes(): void
    {
        $classListPorteurProjs = \App\Filament\Resources\PorteurProjs\Pages\ListPorteurProjs::class;
        $tableHash = md5($classListPorteurProjs);
        session()->forget("tables.{$tableHash}_columns");
        session()->forget("tables.{$tableHash}_has_reordered_columns");
    }

    public function reinitialiser(): void
    {
        $user = Auth::user();
        $user->update(['preferences' => null]);

        $this->form->fill(User::defaultPreferences());
        $this->viderSessionColonnes();

        Notification::make()
            ->title('Paramètres réinitialisés')
            ->body('Les valeurs par défaut ont été restaurées.')
            ->info()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reinitialiser')
                ->label('Réinitialiser')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Réinitialiser les paramètres ?')
                ->modalDescription('Tous vos paramètres personnalisés seront remis à leurs valeurs par défaut.')
                ->action(fn () => $this->reinitialiser()),

            Action::make('enregistrer')
                ->label('Enregistrer')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action(fn () => $this->enregistrer()),
        ];
    }

    /**
     * Liste de TOUTES les colonnes possibles pour la sélection "visibles".
     */
    public static function optionsColonnes(): array
    {
        return [
            // Information générale
            'projet.secteur.libelle'         => '1. Secteur',
            'projet.vague.libelle'           => '1. Vague',
            'projet.guichet.libelle'         => '1. Guichet',
            'projet.reference'               => '1. Réf. projet',
            'reference_convention'           => '1. Réf. convention',
            'porteur.raison_sociale'         => '1. Porteur',
            'porteur.cnaps'                  => '1. CNaPS porteur',
            'porteur.nb_salaries'            => '1. Nb salariés porteur',
            'partenaire_noms'                => '1. Partenaire(s)',
            'porteur.responsable_nom'        => '1. Contact',
            'porteur.telephone'              => '1. Tel',
            'porteur.adresse'                => '1. Adresse',
            'porteur.region.libelle'         => '1. Région',
            'projet.intitule'                => '1. Intitulé',

            // Prévisionnel
            'benef_prev_total'               => '2. Bénéf. total (prévu)',
            'benef_prev_h'                   => '2. H (prévu)',
            'benef_prev_f'                   => '2. F (prévu)',
            'benef_prev_jeunes'              => '2. Jeunes (prévu)',
            'prestataire_prevu'              => '2. Prestataire',
            'modules_prevus'                 => '2. Modules',
            'vol_horaire_total_prevu'        => '2. Vol. horaire total',

            // Contractualisation
            'montant_total'                  => '3. Montant total',
            'financement_demande'            => '3. Financement demandé',
            'statut_validation'              => '3. Statut',
            'motifs'                         => '3. Motifs',
            'date_notification'              => '3. Date notification',
            'date_debut'                     => '3. Date début',
            'date_fin'                       => '3. Date fin',
            'dano_type'                      => '3. DANO',

            // Paiement
            'paiement_j1_date'               => '4. Date J1',
            'paiement_j1_montant'            => '4. Montant J1',
            'paiement_j2_date'               => '4. Date J2',
            'paiement_j2_montant'            => '4. Montant J2',
            'paiement_j3_date'               => '4. Date J3',
            'paiement_j3_montant'            => '4. Montant J3',
            'situation_alloc'                => '4. Situation',

            // Alertes
            'niveau_alerte'                  => '5. Niveau alerte',
            'date_relance_1'                 => '5. 1ère relance',
            'date_relance_2'                 => '5. 2ème relance',

            // Terrain
            'date_suivi_terrain'             => '6. Date suivi terrain',
            'observation_suivi'              => '6. Observation suivi',

            // Rapport technique
            'date_arrivee_rapport'           => '7. Arrivée rapport',
            'evaluateur.name'                => '7. Évaluateur',
            'reserve_description'            => '7. Réserve',

            // Réalisation
            'benef_real_total'               => '8. Bénéf. total (réalisé)',
            'benef_real_h'                   => '8. H (réalisé)',
            'benef_real_f'                   => '8. F (réalisé)',
        ];
    }

    /**
     * Sous-ensemble des colonnes qui peuvent être figées (les plus courtes).
     */
    public static function optionsColonnesFigeables(): array
    {
        return [
            'projet.secteur.libelle' => 'Secteur',
            'projet.vague.libelle'   => 'Vague',
            'projet.guichet.libelle' => 'Guichet',
            'projet.reference'       => 'Réf. projet',
            'reference_convention'   => 'Réf. convention',
            'porteur.raison_sociale' => 'Porteur',
            'porteur.cnaps'          => 'CNaPS',
            'projet.intitule'        => 'Intitulé',
            'statut_validation'      => 'Statut',
            'niveau_alerte'          => 'Niveau alerte',
        ];
    }
}
