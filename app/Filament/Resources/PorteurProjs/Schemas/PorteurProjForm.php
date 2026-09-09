<?php

namespace App\Filament\Resources\PorteurProjs\Schemas;

use App\Models\Porteur;
use App\Models\Projet;
use App\Models\StatutProjet;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Formulaire d'édition d'un projet, organisé en 8 sections métier DEES.
 *
 * Chaque onglet correspond à une phase du cycle de vie du projet :
 *   1. Information générale     — Chargé de projet DEES
 *   2. Prévisionnel              — Chargé de projet DEES
 *   3. Contractualisation        — Chargé de projet DEES
 *   4. Paiement                  — DAF uniquement (lecture seule pour les autres)
 *   5. Alertes                   — Automatique + DEES
 *   6. Suivi terrain             — DEES
 *   7. Rapport technique         — Évaluateur
 *   8. Réalisation               — Chargé de projet DEES
 */
class PorteurProjForm
{
    public static function configure(Schema $schema): Schema
    {
        // L'utilisateur peut-il éditer les paiements ? (DAF ou admin)
        $peutEditerPaiement = fn () => Auth::user()?->peutEditerPaiement() ?? false;

        // Mode création vs édition : on masque les onglets 4-8 à la création
        // pour un formulaire simplifié (le reste apparaît après première sauvegarde).
        $enEdition = fn ($record) => $record !== null && $record->exists;

        // Champs obligatoires configurés par le Super Admin dans /admin/parametres
        $champsObligatoires = \App\Models\SystemSetting::get(
            'projet.champs_obligatoires',
            ['porteur_id', 'intitule', 'secteur_id', 'guichet_id']
        );
        $estObligatoire = fn (string $champ) => in_array($champ, $champsObligatoires, true);

        // Verrouillage strict : si projet CLOTURE ou ANNULE → lecture seule (sauf Super Admin)
        $estVerrouille = function ($record) {
            if (! $record || ! $record->exists) return false;
            if (Auth::user()?->isSuperAdmin()) return false;   // Super Admin peut toujours modifier
            return $record->estVerrouille();
        };

        return $schema->components([

            // ═══ BANNIÈRE VERROUILLAGE (visible si CLOTURE/ANNULE et non Super Admin) ═══
            \Filament\Schemas\Components\View::make('filament.components.banniere-verrouillage')
                ->visible(fn ($record) => $record && $record->exists && $record->estVerrouille() && ! Auth::user()?->isSuperAdmin())
                ->columnSpanFull(),

            Tabs::make('sections')->columnSpanFull()->persistTabInQueryString('section')
                ->disabled($estVerrouille)  // ← Verrouille TOUT le formulaire
                ->tabs([

                // ═══════════════ 1. INFORMATION GÉNÉRALE ═══════════════
                Tabs\Tab::make('1. Information générale')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Section::make('Projet & Convention')
                            ->description("Sélectionnez un projet existant OU cliquez sur \"+\" pour créer un nouveau projet sans quitter la page.")
                            ->columns(2)
                            ->schema([
                                Select::make('projet_id')->label('Projet')
                                    ->options(fn () => Projet::orderBy('reference')->pluck('reference', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    // ─── Bouton "+ Nouveau projet" inline ───
                                    ->createOptionForm([
                                        Section::make('Identification du projet')
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('reference')
                                                    ->label('Référence projet')
                                                    ->required()
                                                    ->maxLength(50)
                                                    ->unique('projet', 'reference')
                                                    ->helperText('Ex: STELLARIX_2026_001 — sera normalisée automatiquement')
                                                    ->columnSpan(1),
                                                TextInput::make('intitule')
                                                    ->label('Intitulé du projet')
                                                    ->required()
                                                    ->maxLength(300)
                                                    ->columnSpan(1),
                                            ]),

                                        Section::make('Classification')
                                            ->columns(2)
                                            ->schema([
                                                Select::make('guichet_id')
                                                    ->label('Guichet')
                                                    ->options(fn () => \App\Models\Guichet::orderBy('libelle')->pluck('libelle', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->required($estObligatoire('guichet_id')),
                                                Select::make('secteur_id')
                                                    ->label('Secteur')
                                                    ->options(fn () => \App\Models\Secteur::orderBy('libelle')->pluck('libelle', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->required($estObligatoire('secteur_id')),
                                                Select::make('vague_id')
                                                    ->label('Vague')
                                                    ->options(fn () => \App\Models\Vague::orderBy('libelle')->pluck('libelle', 'id'))
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('region_id')
                                                    ->label('Région')
                                                    ->options(fn () => \App\Models\Region::orderBy('libelle')->pluck('libelle', 'id'))
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('statut_projet_id')
                                                    ->label('Statut initial')
                                                    ->options(fn () => StatutProjet::orderBy('ordre')->pluck('libelle', 'id'))
                                                    ->default(fn () => StatutProjet::where('code', 'stand_by')->value('id'))
                                                    ->required(),
                                            ]),

                                        Section::make('Dates prévues')
                                            ->columns(2)
                                            ->collapsed()
                                            ->schema([
                                                DatePicker::make('date_debut')
                                                    ->label('Date de début prévue')
                                                    ->native(false),
                                                DatePicker::make('date_fin')
                                                    ->label('Date de fin prévue')
                                                    ->native(false),
                                            ]),
                                    ])
                                    ->createOptionModalHeading('+ Créer un nouveau projet')
                                    ->createOptionAction(fn ($action) => $action
                                        ->modalWidth('4xl')
                                        ->modalSubmitActionLabel('Créer le projet')
                                        ->modalCancelActionLabel('Annuler'))
                                    ->createOptionUsing(function (array $data): int {
                                        $data['created_by'] = Auth::id();
                                        $projet = Projet::create($data);
                                        \Filament\Notifications\Notification::make()
                                            ->title('Projet créé')
                                            ->body("« {$projet->reference} » a été ajouté. Vous pouvez continuer la saisie.")
                                            ->success()
                                            ->send();
                                        return $projet->id;
                                    }),

                                TextInput::make('reference_convention')
                                    ->label('Référence convention')
                                    ->required($estObligatoire('reference_convention'))
                                    ->maxLength(255)
                                    ->helperText('Optionnelle — peut être ajoutée plus tard'),
                            ]),

                        Section::make('Porteur (entreprise)')
                            ->description("Entreprise principale porteuse du projet. Si votre porteur n'existe pas encore, cliquez sur le bouton \"+\" à droite du champ pour le créer sans quitter la page.")
                            ->columns(2)
                            ->schema([
                                Select::make('porteur_id')->label('Porteur')
                                    ->options(fn () => Porteur::orderBy('raison_sociale')->pluck('raison_sociale', 'id'))
                                    ->required($estObligatoire('porteur_id'))
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull()
                                    // ─── Bouton "+ Nouveau porteur" inline ───
                                    ->createOptionForm([
                                        Section::make('Identité de l\'entreprise')
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('raison_sociale')
                                                    ->label('Raison sociale')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                TextInput::make('sigle')
                                                    ->label('Sigle / Acronyme')
                                                    ->maxLength(50),
                                                Select::make('forme_juridique')
                                                    ->label('Forme juridique')
                                                    ->options([
                                                        'SA'    => 'SA',
                                                        'SARL'  => 'SARL',
                                                        'SAS'   => 'SAS',
                                                        'EI'    => 'Entreprise Individuelle',
                                                        'GIE'   => 'GIE',
                                                        'ONG'   => 'ONG',
                                                        'Autre' => 'Autre',
                                                    ])
                                                    ->native(false),
                                                TextInput::make('nif')
                                                    ->label('NIF')
                                                    ->maxLength(50),
                                                TextInput::make('cnaps')
                                                    ->label('CNaPS')
                                                    ->maxLength(100),
                                                TextInput::make('nb_salaries')
                                                    ->label('Nombre de salariés')
                                                    ->numeric()
                                                    ->minValue(0),
                                            ]),

                                        Section::make('Coordonnées')
                                            ->columns(2)
                                            ->schema([
                                                Select::make('secteur_id')
                                                    ->label('Secteur d\'activité')
                                                    ->options(fn () => \App\Models\Secteur::orderBy('libelle')->pluck('libelle', 'id'))
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('region_id')
                                                    ->label('Région')
                                                    ->options(fn () => \App\Models\Region::orderBy('libelle')->pluck('libelle', 'id'))
                                                    ->searchable()
                                                    ->preload(),
                                                TextInput::make('adresse')
                                                    ->label('Adresse complète')
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                TextInput::make('ville')
                                                    ->label('Ville')
                                                    ->maxLength(100),
                                                TextInput::make('telephone')
                                                    ->label('Téléphone')
                                                    ->tel()
                                                    ->maxLength(50),
                                                TextInput::make('email')
                                                    ->label('Email')
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                            ]),

                                        Section::make('Contact / Responsable')
                                            ->columns(2)
                                            ->collapsed()
                                            ->schema([
                                                TextInput::make('responsable_nom')
                                                    ->label('Nom du responsable')
                                                    ->maxLength(255),
                                                TextInput::make('responsable_fonction')
                                                    ->label('Fonction')
                                                    ->maxLength(100),
                                            ]),
                                    ])
                                    ->createOptionModalHeading('+ Créer un nouveau porteur')
                                    ->createOptionAction(fn ($action) => $action
                                        ->modalWidth('4xl')
                                        ->modalSubmitActionLabel('Créer le porteur')
                                        ->modalCancelActionLabel('Annuler'))
                                    ->createOptionUsing(function (array $data): int {
                                        $data['created_by'] = Auth::id();
                                        $porteur = Porteur::create($data);
                                        \Filament\Notifications\Notification::make()
                                            ->title('Porteur créé')
                                            ->body("« {$porteur->raison_sociale} » a été ajouté à la liste des entreprises.")
                                            ->success()
                                            ->send();
                                        return $porteur->id;
                                    }),
                            ]),

                        // Partenaires illimités via Repeater
                        Section::make('Partenaires')
                            ->description("Une entreprise peut avoir un nombre illimité de partenaires.")
                            ->schema([
                                Repeater::make('partenaires')
                                    ->relationship()
                                    ->label('')
                                    ->columns(3)
                                    ->addActionLabel('+ Ajouter un partenaire')
                                    ->collapsible()
                                    ->reorderableWithButtons()
                                    ->itemLabel(fn (array $state): ?string => $state['nom'] ?? null)
                                    ->schema([
                                        TextInput::make('nom')
                                            ->label('Nom du partenaire')
                                            ->required()->maxLength(200)->columnSpan(2),
                                        TextInput::make('cnaps')->label('CNaPS')->maxLength(100),
                                        TextInput::make('nb_salaries')->label('Nb salariés')
                                            ->numeric()->minValue(0)->default(0),
                                        TextInput::make('contact')->label('Contact')->maxLength(100),
                                    ]),
                            ]),
                    ]),

                // ═══════════════ 2. DONNÉES PRÉVISIONNELLES ═══════════════
                Tabs\Tab::make('2. Prévisionnel')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Section::make('Bénéficiaires prévus')
                            ->description('Estimation initiale des bénéficiaires.')
                            ->schema([
                                Repeater::make('benefs')
                                    ->relationship(modifyQueryUsing: fn ($query) => $query->where('type', 'prevu'))
                                    ->label('')
                                    ->maxItems(1)
                                    ->deletable(false)
                                    ->addable(false)
                                    ->defaultItems(1)
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('type')->default('prevu')->hidden(),
                                        TextInput::make('total')->label('Nb bénéf total')->numeric()->default(0),
                                        TextInput::make('h')->label('Hommes')->numeric()->default(0),
                                        TextInput::make('f')->label('Femmes')->numeric()->default(0),
                                        TextInput::make('jeunes')->label('Jeunes')->numeric()->default(0),
                                        TextInput::make('fpe')->label('FPE (formation pré-emploi)')->numeric()->default(0),
                                        TextInput::make('cadres')->label('Femmes cadres')->numeric()->default(0),
                                    ]),
                            ]),

                        Section::make('Formation prévue')
                            ->columns(2)
                            ->schema([
                                Textarea::make('formation_prevue_prestataire')
                                    ->label('Prestataire')->rows(2)
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn ($component, $record) =>
                                        $component->state($record?->formations?->where('type', 'prevu')?->first()?->prestataires?->pluck('nom')?->join(', '))
                                    ),
                                TextInput::make('formation_prevue_vol_total')
                                    ->label('Volume horaire total')->numeric()->suffix('h')
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn ($component, $record) =>
                                        $component->state($record?->formations?->where('type', 'prevu')?->first()?->volume_horaire_total)
                                    ),
                                Textarea::make('formation_prevue_modules')
                                    ->label('Modules de formation')->rows(3)->columnSpanFull()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn ($component, $record) =>
                                        $component->state($record?->formations?->where('type', 'prevu')?->first()?->modules?->pluck('intitule')?->join('; '))
                                    ),
                                Textarea::make('formation_prevue_formateur')
                                    ->label('Formateur(s)')->rows(2)->columnSpanFull()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn ($component, $record) =>
                                        $component->state($record?->formations?->where('type', 'prevu')?->first()?->formateurs?->pluck('nom')?->join(', '))
                                    ),
                            ]),
                    ]),

                // ═══════════════ 3. CONTRACTUALISATION ═══════════════
                Tabs\Tab::make('3. Contractualisation')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Section::make('Financement (Ariary)')
                            ->columns(3)
                            ->schema([
                                TextInput::make('montant_total')->label('Montant total')->numeric()->default(0)->suffix('Ar'),
                                TextInput::make('financement_demande')->label('Financement demandé')->numeric()->default(0)->suffix('Ar'),
                                TextInput::make('dt_mobilise')->label('DT mobilisé')->numeric()->default(0)->suffix('Ar'),
                                TextInput::make('fonds_additionnel')->label('Fonds additionnels')->numeric()->default(0)->suffix('Ar'),
                                TextInput::make('fonds_mutualise')->label('Fonds mutualisé')->numeric()->default(0)->suffix('Ar'),
                                TextInput::make('financement_autre')->label('Financement (autre)')->numeric()->default(0)->suffix('Ar'),
                            ]),

                        Section::make('Validation & Statut')
                            ->columns(2)
                            ->schema([
                                Select::make('statut_validation')
                                    ->label('Statut du projet')
                                    ->options([
                                        'stand_by'              => 'Stand by',
                                        'attente_pieces_regul'  => 'Attente pièces régul.',
                                        'validation_financiere' => 'Validation financière',
                                        'formation_encours'     => 'Formation en cours',
                                        'cloture'               => 'Clôturé',
                                        'annule'                => 'Annulé',
                                    ])
                                    ->default('stand_by')
                                    ->required(),
                                Textarea::make('appreciation_evaluateur')
                                    ->label('Appréciation évaluateur')->rows(2),
                                Textarea::make('motifs')
                                    ->label("Motifs (si refusé ou non validé)")
                                    ->rows(2)->columnSpanFull(),
                            ]),

                        Section::make('Dates')
                            ->columns(3)
                            ->schema([
                                DatePicker::make('date_notification')->label('Date notification'),
                                DatePicker::make('date_envoi_convention')->label("Date d'envoi convention"),
                                DatePicker::make('date_reception_convention')->label('Date réception convention'),
                                DatePicker::make('date_debut')->label('Date début'),
                                DatePicker::make('date_fin')->label('Date fin'),
                            ]),

                        Section::make('DANO')
                            ->description('Le DANO existe uniquement en cas de changement (date, formateur, réaménagement budgétaire).')
                            ->columns(2)
                            ->schema([
                                Select::make('dano_type')
                                    ->label('Type de DANO')
                                    ->options([
                                        ''                         => '— Aucun DANO —',
                                        'changement_date'          => 'Changement de date',
                                        'changement_formateur'     => 'Changement de formateur',
                                        'reamenagement_budgetaire' => 'Réaménagement budgétaire',
                                    ])
                                    ->native(false),
                                Textarea::make('dano_description')
                                    ->label('Description du DANO')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                // ═══════════════ 4. PAIEMENT (DAF uniquement) ═══════════════
                Tabs\Tab::make('4. Paiement')
                    ->icon('heroicon-o-banknotes')
                    ->visible($enEdition)  // Masqué à la création
                    ->badge(fn () => Auth::user()?->peutEditerPaiement() ? 'DAF' : 'Lecture seule')
                    ->badgeColor(fn () => Auth::user()?->peutEditerPaiement() ? 'success' : 'gray')
                    ->schema([
                        Section::make('Tranches de paiement (J1, J2, J3)')
                            ->description(
                                Auth::user()?->peutEditerPaiement()
                                    ? "Vous êtes membre de la DAF — vous pouvez saisir les paiements."
                                    : "Section réservée à l'équipe DAF. Vous êtes en lecture seule."
                            )
                            ->schema([
                                Repeater::make('paiements')
                                    ->relationship()
                                    ->label('')
                                    ->columns(3)
                                    ->addActionLabel('+ Ajouter un paiement')
                                    ->disabled(fn () => ! (Auth::user()?->peutEditerPaiement() ?? false))
                                    ->schema([
                                        Select::make('ligne')
                                            ->label('Tranche')
                                            ->options(['J1' => 'J1', 'J2' => 'J2', 'J3' => 'J3'])
                                            ->required(),
                                        DatePicker::make('date_paiement')->label('Date paiement')->required(),
                                        TextInput::make('montant')->label('Montant')->numeric()->required()->suffix('Ar'),
                                    ]),
                            ]),

                        Section::make('Situation allocation')
                            ->columns(2)
                            ->schema([
                                Select::make('situation_alloc')
                                    ->label('Situation')
                                    ->options([
                                        'non_verse' => 'Non versé',
                                        'partiel'   => 'Partiel',
                                        'total'     => 'Total',
                                        'solde'     => 'Clôturé / Soldé',
                                        'annule'    => 'Annulé',
                                        'remboursm' => 'Remboursement',
                                    ])
                                    ->helperText('Guichet EQUITÉ : "Clôturé" seulement si J2 ou J3 versé. Sinon "En cours" si J1.')
                                    ->disabled(fn () => ! (Auth::user()?->peutEditerPaiement() ?? false)),
                            ]),
                    ]),

                // ═══════════════ 5. ALERTES ═══════════════
                Tabs\Tab::make('5. Alertes')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible($enEdition)  // Masqué à la création
                    ->schema([
                        Section::make('Niveau d\'alerte')
                            ->description('Calculé automatiquement selon les jours écoulés depuis la date de fin de convention. Vous pouvez le forcer manuellement.')
                            ->columns(2)
                            ->schema([
                                Select::make('niveau_alerte')
                                    ->label('Niveau alerte')
                                    ->options([
                                        'verte'  => '🟢 Verte (30-59j dépassés)',
                                        'orange' => '🟠 Orange (60-89j dépassés)',
                                        'rouge'  => '🔴 Rouge (90j+ dépassés)',
                                    ])
                                    ->default('verte'),
                            ]),

                        Section::make('Dates de relance (alertes rouges)')
                            ->columns(2)
                            ->schema([
                                DatePicker::make('date_relance_1')->label('1ère relance'),
                                DatePicker::make('date_relance_2')->label('2ème relance'),
                                DatePicker::make('date_mise_en_demeure')->label('Réception mise en demeure'),
                                DatePicker::make('date_resiliation')->label('Réception résiliation'),
                            ]),

                        Textarea::make('observations')
                            ->label('Observation')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                // ═══════════════ 6. SUIVI TERRAIN ═══════════════
                Tabs\Tab::make('6. Terrain')
                    ->icon('heroicon-o-map-pin')
                    ->visible($enEdition)  // Masqué à la création
                    ->schema([
                        Section::make('Suivi sur le terrain')
                            ->columns(2)
                            ->schema([
                                DatePicker::make('date_formation_contractants')
                                    ->label('Date formation des contractants'),
                                DatePicker::make('date_suivi_terrain')
                                    ->label('Date suivi terrain'),
                                Textarea::make('observation_suivi')
                                    ->label('Observation suivi terrain')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                // ═══════════════ 7. RAPPORT TECHNIQUE ═══════════════
                Tabs\Tab::make('7. Rapport technique')
                    ->icon('heroicon-o-document-chart-bar')
                    ->visible($enEdition)  // Masqué à la création
                    ->schema([
                        Section::make('Rapport & Évaluateur')
                            ->columns(2)
                            ->schema([
                                DatePicker::make('date_arrivee_rapport')
                                    ->label("Date d'arrivée du rapport à la DEES"),
                                Select::make('evaluateur_id')
                                    ->label('Évaluateur')
                                    ->options(function () {
                                        // Défensif : si le rôle 'evaluateur' n'existe pas, on retourne une liste vide
                                        // au lieu de crasher (Spatie throw RoleDoesNotExist).
                                        try {
                                            return User::role('evaluateur')->pluck('name', 'id');
                                        } catch (\Throwable) {
                                            return User::all()->pluck('name', 'id');
                                        }
                                    })
                                    ->searchable(),
                                DatePicker::make('date_transfert_evaluateur')
                                    ->label('Date de transfert vers évaluateur'),
                                DatePicker::make('date_debut_traitement')
                                    ->label('Date de début de traitement'),
                            ]),

                        Section::make('Réserves')
                            ->columns(2)
                            ->schema([
                                Textarea::make('reserve_description')
                                    ->label('Réserve du projet')
                                    ->rows(3)->columnSpanFull(),
                                DatePicker::make('date_envoi_reserve')
                                    ->label('Date envoi réserve vers le porteur'),
                                TextInput::make('situation_reserves')
                                    ->label('Situation des réserves')
                                    ->maxLength(200),
                                DatePicker::make('date_relance_reserve_1')
                                    ->label("Date d'envoi 1ère relance"),
                                DatePicker::make('date_relance_reserve_2')
                                    ->label("Date d'envoi 2ème relance"),
                            ]),

                        Section::make('Validation & Transmission')
                            ->columns(2)
                            ->schema([
                                DatePicker::make('date_validation_evaluateur')
                                    ->label('Date de validation évaluateur'),
                                DatePicker::make('date_transmission_daf')
                                    ->label("Date de transmission vers l'équipe DAF"),
                                Textarea::make('observations_evaluation')
                                    ->label('Observations (évaluation)')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                // ═══════════════ 8. RÉALISATION ═══════════════
                Tabs\Tab::make('8. Réalisation')
                    ->icon('heroicon-o-check-badge')
                    ->visible($enEdition)  // Masqué à la création
                    ->schema([
                        Section::make('Bénéficiaires effectivement formés')
                            ->description('Chiffres réels après réalisation du projet.')
                            ->schema([
                                Repeater::make('benefs_realises')
                                    ->relationship('benefs', modifyQueryUsing: fn ($query) => $query->where('type', 'realise'))
                                    ->label('')
                                    ->maxItems(1)
                                    ->deletable(false)
                                    ->addable(false)
                                    ->defaultItems(1)
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('type')->default('realise')->hidden(),
                                        TextInput::make('total')->label('Nb bénéf total formé')->numeric()->default(0),
                                        TextInput::make('h')->label('Hommes')->numeric()->default(0),
                                        TextInput::make('f')->label('Femmes')->numeric()->default(0),
                                        TextInput::make('jeunes')->label('Jeunes')->numeric()->default(0),
                                        TextInput::make('fpe')->label('FPE')->numeric()->default(0),
                                        TextInput::make('cadres')->label('Femmes cadres formées')->numeric()->default(0),
                                    ]),
                            ]),

                        Section::make('Formation réalisée')
                            ->columns(2)
                            ->schema([
                                Textarea::make('formation_realisee_prestataire')
                                    ->label('Prestataire')->rows(2)
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn ($component, $record) =>
                                        $component->state($record?->formations?->where('type', 'realise')?->first()?->prestataires?->pluck('nom')?->join(', '))
                                    ),
                                TextInput::make('formation_realisee_vol_total')
                                    ->label('Volume horaire total')->numeric()->suffix('h')
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn ($component, $record) =>
                                        $component->state($record?->formations?->where('type', 'realise')?->first()?->volume_horaire_total)
                                    ),
                                Textarea::make('formation_realisee_modules')
                                    ->label('Modules réalisés')->rows(3)->columnSpanFull()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn ($component, $record) =>
                                        $component->state($record?->formations?->where('type', 'realise')?->first()?->modules?->pluck('intitule')?->join('; '))
                                    ),
                                Textarea::make('formation_realisee_formateur')
                                    ->label('Formateur(s)')->rows(2)->columnSpanFull()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn ($component, $record) =>
                                        $component->state($record?->formations?->where('type', 'realise')?->first()?->formateurs?->pluck('nom')?->join(', '))
                                    ),
                            ]),
                    ]),
            ]),
        ]);
    }
}
