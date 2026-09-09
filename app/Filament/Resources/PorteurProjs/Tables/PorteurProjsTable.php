<?php

namespace App\Filament\Resources\PorteurProjs\Tables;

use App\Filament\Resources\PorteurProjs\PorteurProjResource;
use App\Models\PorteurProj;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Table principale organisée en 8 GROUPES DE COLONNES colorés
 * qui reproduisent la structure du fichier Excel DEES :
 *
 *   1. 🔵 Information générale  (17 colonnes)
 *   2. 🟢 Prévisionnel          (11 colonnes)
 *   3. ⚪ Contractualisation    (15 colonnes)
 *   4. ⚫ Paiement              (8 colonnes, DAF)
 *   5. 🟡 Alerte rouge          (6 colonnes)
 *   6. 🟠 Terrain               (3 colonnes)
 *   7. 🟣 Rapport technique     (12 colonnes)
 *   8. 🟤 Réalisation           (11 colonnes)
 *
 * Vue par défaut : 7 colonnes essentielles.
 * Toutes les autres colonnes sont activables via le bouton "Colonnes".
 */
class PorteurProjsTable
{
    /** Couleurs Filament pour chaque statut projet DEES */
    private const STATUT_COLORS = [
        'stand_by'                => 'gray',
        'attente_pieces_regul'    => 'warning',
        'validation_financiere'   => 'info',
        'formation_encours'       => 'primary',
        'cloture'                 => 'success',
        'annule'                  => 'danger',
        'valide'                  => 'success',
        'refuse'                  => 'danger',
        'incomplet'               => 'gray',
    ];

    public static function configure(Table $table): Table
    {
        // ═══ PRÉFÉRENCES UTILISATEUR ═══
        // Chaque utilisateur a ses propres colonnes visibles/figées/pagination via /admin/parametres.
        // Fallback sur User::defaultPreferences() si pas encore configuré.
        $user = Auth::user();
        $defaults = User::defaultPreferences();
        $colonnesVisibles = $user?->pref('colonnes_visibles') ?? $defaults['colonnes_visibles'];
        $colonnesFigees   = $user?->pref('colonnes_figees')   ?? $defaults['colonnes_figees'];
        $pagination       = (int) ($user?->pref('pagination_defaut') ?? 25);
        $triColonne       = $user?->pref('tri_colonne') ?? 'created_at';
        $triSens          = $user?->pref('tri_sens') ?? 'desc';

        // Helper : une colonne est-elle cachée par défaut ?
        // OUI si elle n'est PAS dans la liste "colonnes_visibles" des préférences.
        $cachee = fn (string $name) => ! in_array($name, $colonnesVisibles, true);

        // Helper : cette colonne est-elle figée à gauche ?
        $estFigee = fn (string $name) => in_array($name, $colonnesFigees, true);

        // ═══ COLONNES FIGÉES (sticky) ═══
        // Position calculée dynamiquement selon l'ordre dans les préférences.
        // Chaque colonne prend 160px par défaut ; ombre légère sur la DERNIÈRE colonne figée.
        $largeurFigee = 160;
        $positions = [];
        foreach ($colonnesFigees as $i => $name) {
            $positions[$name] = [
                'left'    => $i * $largeurFigee,
                'isLast'  => $i === count($colonnesFigees) - 1,
            ];
        }

        // Styles sticky :
        // - background : cache les cellules non-figées qui glissent en-dessous
        // - white-space: nowrap : évite les retours à la ligne dans une largeur restreinte
        // - min-width sans max-width : laisse le contenu respirer si plus large
        // - z-index bas : reste sous les modals/dropdowns Filament
        $styleHeader = function (string $name) use ($positions, $largeurFigee) {
            if (! isset($positions[$name])) return [];
            $left = $positions[$name]['left'];
            $ombre = $positions[$name]['isLast'] ? ' box-shadow: 4px 0 6px -4px rgba(0,0,0,0.18);' : '';
            return ['style' =>
                "position: sticky; left: {$left}px; z-index: 20; min-width: {$largeurFigee}px; white-space: nowrap; background: rgb(243 244 246); border-right: 1px solid rgb(209 213 219);" . $ombre
            ];
        };

        $styleCell = function (string $name) use ($positions, $largeurFigee) {
            if (! isset($positions[$name])) return [];
            $left = $positions[$name]['left'];
            $ombre = $positions[$name]['isLast'] ? ' box-shadow: 4px 0 6px -4px rgba(0,0,0,0.12);' : '';
            return ['style' =>
                "position: sticky; left: {$left}px; z-index: 10; min-width: {$largeurFigee}px; background: white; border-right: 1px solid rgb(229 231 235);" . $ombre
            ];
        };

        // ═══ ANCIENS HELPERS (compat avec le code existant plus bas) ═══
        // Conservés au cas où d'autres colonnes veulent être figées manuellement.
        $stickyHeader = fn (int $left, int $width) =>
            "position: sticky; left: {$left}px; z-index: 2; min-width: {$width}px; max-width: {$width}px; background: rgb(243 244 246); border-right: 1px solid rgb(229 231 235);";
        $stickyCell = fn (int $left, int $width) =>
            "position: sticky; left: {$left}px; z-index: 1; min-width: {$width}px; max-width: {$width}px; background: white; border-right: 1px solid rgb(229 231 235);";
        $stickyHeaderLast = fn (int $left, int $width) =>
            $stickyHeader($left, $width) . ' box-shadow: 4px 0 6px -4px rgba(0,0,0,0.15);';
        $stickyCellLast = fn (int $left, int $width) =>
            $stickyCell($left, $width) . ' box-shadow: 4px 0 6px -4px rgba(0,0,0,0.10);';

        return $table
            ->columns([

                // ═══════════════════════════════════════════════════════════
                // 1️ INFORMATION GÉNÉRALE  
                // ═══════════════════════════════════════════════════════════
                ColumnGroup::make('1. Information générale')
                    ->columns([
                        TextColumn::make('projet.secteur.libelle')
                            ->label('Secteur')->sortable()->searchable()
                            ->badge()->color('gray')
                            ->toggleable(isToggledHiddenByDefault: $cachee('projet.secteur.libelle'))
                            ->extraHeaderAttributes($styleHeader('projet.secteur.libelle'))
                            ->extraCellAttributes($styleCell('projet.secteur.libelle')),

                        TextColumn::make('projet.vague.libelle')
                            ->label('Vague')->sortable()->searchable()
                            ->badge()->color('gray')
                            ->toggleable(isToggledHiddenByDefault: $cachee('projet.vague.libelle'))
                            ->extraHeaderAttributes($styleHeader('projet.vague.libelle'))
                            ->extraCellAttributes($styleCell('projet.vague.libelle')),

                        TextColumn::make('projet.guichet.libelle')
                            ->label('Guichet')->sortable()->searchable()
                            ->toggleable(isToggledHiddenByDefault: $cachee('projet.guichet.libelle'))
                            ->extraHeaderAttributes($styleHeader('projet.guichet.libelle'))
                            ->extraCellAttributes($styleCell('projet.guichet.libelle')),

                        TextColumn::make('projet.reference')
                            ->label('Réf. projet')->searchable()->sortable()
                            ->copyable()->weight('medium')
                            ->toggleable(isToggledHiddenByDefault: $cachee('projet.reference'))
                            ->extraHeaderAttributes($styleHeader('projet.reference'))
                            ->extraCellAttributes($styleCell('projet.reference')),

                        TextColumn::make('reference_convention')
                            ->label('Réf. convention')->searchable()->sortable()
                            ->copyable()->limit(30)
                            ->tooltip(fn (PorteurProj $r) => $r->reference_convention)
                            ->toggleable(isToggledHiddenByDefault: $cachee('reference_convention'))
                            ->extraHeaderAttributes($styleHeader('reference_convention'))
                            ->extraCellAttributes($styleCell('reference_convention')),

                        TextColumn::make('porteur.raison_sociale')
                            ->label('Porteur')->searchable()->sortable()
                            ->weight('medium')->limit(35)
                            ->tooltip(fn (PorteurProj $r) => $r->porteur?->raison_sociale)
                            ->toggleable(isToggledHiddenByDefault: $cachee('porteur.raison_sociale'))
                            ->extraHeaderAttributes($styleHeader('porteur.raison_sociale'))
                            ->extraCellAttributes($styleCell('porteur.raison_sociale')),

                        TextColumn::make('porteur.cnaps')
                            ->label('CNaPS porteur')->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('porteur.nb_salaries')
                            ->label('Nb salariés porteur')
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('partenaires_count')
                            ->label('Nb partenaires')
                            ->badge()
                            ->getStateUsing(fn (PorteurProj $r) => $r->partenaires->count())
                            ->color(fn (int $state) => match (true) {
                                $state === 0  => 'gray',
                                $state <= 3   => 'info',
                                $state <= 10  => 'success',
                                default       => 'warning',
                            })
                            ->formatStateUsing(fn (int $state) => $state === 0 ? '—' : (string) $state)
                            ->tooltip(fn (PorteurProj $r) => $r->partenaires->isEmpty()
                                ? 'Aucun partenaire'
                                : $r->partenaires->pluck('nom')->take(5)->join(', ')
                                    . ($r->partenaires->count() > 5 ? ' …' : ''))
                            ->alignCenter()
                            ->toggleable(isToggledHiddenByDefault: $cachee('partenaires_count')),

                        TextColumn::make('partenaire_noms')
                            ->label('Partenaire(s)')
                            ->getStateUsing(fn (PorteurProj $r) => $r->partenaires->pluck('nom')->join(', ') ?: null)
                            ->limit(40)
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('partenaire_cnaps')
                            ->label('CNaPS partenaire')
                            ->getStateUsing(fn (PorteurProj $r) => $r->partenaires->pluck('cnaps')->filter()->join(', ') ?: null)
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('partenaire_salaries')
                            ->label('Nb salariés partenaire')
                            ->getStateUsing(fn (PorteurProj $r) => $r->partenaires->pluck('nb_salaries')->filter()->join(', ') ?: null)
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('porteur.responsable_nom')
                            ->label('Contact')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('porteur.telephone')
                            ->label('Tel')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('porteur.adresse')
                            ->label('Adresse')->limit(30)
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('porteur.region.libelle')
                            ->label('Région')->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('projet.intitule')
                            ->label('Intitulé')->searchable()->limit(50)
                            ->tooltip(fn (PorteurProj $r) => $r->projet?->intitule)
                            ->color('gray')
                            ->toggleable(isToggledHiddenByDefault: $cachee('projet.intitule'))
                            ->extraHeaderAttributes($styleHeader('projet.intitule'))
                            ->extraCellAttributes($styleCell('projet.intitule')),
                    ]),

                // ═══════════════════════════════════════════════════════════
                // 2 DONNÉES PRÉVISIONNELLES  
                // ═══════════════════════════════════════════════════════════
                ColumnGroup::make('2. Prévisionnel')
                    ->columns([
                        TextColumn::make('benef_prev_total')
                            ->label('Nb bénéf. total')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','prevu')->first()?->total)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('benef_prev_h')
                            ->label('H')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','prevu')->first()?->h)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('benef_prev_f')
                            ->label('F')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','prevu')->first()?->f)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('benef_prev_jeunes')
                            ->label('Jeunes')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','prevu')->first()?->jeunes)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('benef_prev_fpe')
                            ->label('FPE')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','prevu')->first()?->fpe)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('benef_prev_cadres')
                            ->label('Femmes cadres')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','prevu')->first()?->cadres)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('prestataire_prevu')
                            ->label('Prestataire')
                            ->getStateUsing(fn (PorteurProj $r) => $r->formations->where('type','prevu')->first()?->prestataires?->pluck('nom')?->join(', '))
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('modules_prevus')
                            ->label('Modules')->limit(40)
                            ->getStateUsing(fn (PorteurProj $r) => $r->formations->where('type','prevu')->first()?->modules?->pluck('intitule')?->join('; '))
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('vol_horaire_par_module_prevu')
                            ->label('Vol. h/module')
                            ->getStateUsing(fn (PorteurProj $r) => $r->formations->where('type','prevu')->first()?->formMods?->first()?->volume_horaire)
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('formateur_prevu')
                            ->label('Formateur')
                            ->getStateUsing(fn (PorteurProj $r) => $r->formations->where('type','prevu')->first()?->formateurs?->pluck('nom')?->join(', '))
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('vol_horaire_total_prevu')
                            ->label('Vol. h. total')
                            ->getStateUsing(fn (PorteurProj $r) => $r->formations->where('type','prevu')->first()?->volume_horaire_total)
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),

                // ═══════════════════════════════════════════════════════════
                // 3️ CONTRACTUALISATION & MISE EN ŒUVRE 
                // ═══════════════════════════════════════════════════════════
                ColumnGroup::make('3. Contractualisation & mise en œuvre')
                    ->columns([
                        TextColumn::make('montant_total')
                            ->label('Montant total')->numeric()->suffix(' Ar')->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('financement_demande')
                            ->label('Financement demandé')->numeric()->suffix(' Ar')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('dt_mobilise')
                            ->label('DT mobilisé')->numeric()->suffix(' Ar')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('fonds_additionnel')
                            ->label('Fonds additionnels')->numeric()->suffix(' Ar')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('fonds_mutualise')
                            ->label('Fonds mutualisé')->numeric()->suffix(' Ar')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('financement_autre')
                            ->label('Financement (autre)')->numeric()->suffix(' Ar')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('appreciation_evaluateur')
                            ->label('Appréciation évaluateur')->limit(30)
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('statut_validation')
                            ->label('Statut')
                            ->badge()
                            ->sortable()
                            ->color(fn (?string $state) => self::STATUT_COLORS[$state] ?? 'gray')
                            ->formatStateUsing(fn (?string $state, PorteurProj $r) =>
                                $r->projet?->statut?->libelle ?? ucfirst(str_replace('_', ' ', $state ?? '—'))
                            )
                            ->toggleable(isToggledHiddenByDefault: $cachee('statut_validation'))
                            ->extraHeaderAttributes($styleHeader('statut_validation'))
                            ->extraCellAttributes($styleCell('statut_validation')),

                        TextColumn::make('motifs')
                            ->label('Motifs')->limit(30)
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_notification')
                            ->label('Date notification')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_envoi_convention')
                            ->label("Date d'envoi convention")->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_reception_convention')
                            ->label('Date réception convention')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_debut')
                            ->label('Date début')->date('d/m/Y')->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_fin')
                            ->label('Date fin')->date('d/m/Y')->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('dano_type')
                            ->label('DANO')->limit(30)
                            ->badge()->color('warning')
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),

                // ═══════════════════════════════════════════════════════════
                // 4️ PAIEMENT (pour l'equipe de DAF) 
                // ═══════════════════════════════════════════════════════════
                ColumnGroup::make('4. Paiement (DAF)')
                    ->columns([
                        TextColumn::make('paiement_j1_date')
                            ->label('Date paiement J1')
                            ->getStateUsing(fn (PorteurProj $r) =>
                                optional($r->paiements->where('ligne','J1')->first()?->date_paiement)?->format('d/m/Y'))
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('paiement_j1_montant')
                            ->label('Montant J1')
                            ->getStateUsing(fn (PorteurProj $r) => $r->paiements->where('ligne','J1')->first()?->montant)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('paiement_j2_date')
                            ->label('Date paiement J2')
                            ->getStateUsing(fn (PorteurProj $r) =>
                                optional($r->paiements->where('ligne','J2')->first()?->date_paiement)?->format('d/m/Y'))
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('paiement_j2_montant')
                            ->label('Montant J2')
                            ->getStateUsing(fn (PorteurProj $r) => $r->paiements->where('ligne','J2')->first()?->montant)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('paiement_j3_date')
                            ->label('Date paiement J3')
                            ->getStateUsing(fn (PorteurProj $r) =>
                                optional($r->paiements->where('ligne','J3')->first()?->date_paiement)?->format('d/m/Y'))
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('paiement_j3_montant')
                            ->label('Montant J3')
                            ->getStateUsing(fn (PorteurProj $r) => $r->paiements->where('ligne','J3')->first()?->montant)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('allocation_consommee')
                            ->label('Allocation consommée')
                            ->getStateUsing(fn (PorteurProj $r) =>
                                $r->paiements->where('is_annule', false)->sum('montant'))
                            ->numeric()->suffix(' Ar')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('situation_alloc')
                            ->label('Situation')
                            ->badge()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),

                // ═══════════════════════════════════════════════════════════
                // 5 ALERTE ROUGE 
                // ═══════════════════════════════════════════════════════════
                ColumnGroup::make('5. Alerte rouge')
                    ->columns([
                        TextColumn::make('niveau_alerte')
                            ->label('Situation alerte')
                            ->badge()
                            ->color(fn (?string $s) => match ($s) {
                                'rouge' => 'danger', 'orange' => 'warning', default => 'success',
                            })
                            ->formatStateUsing(fn (?string $s) => match ($s) {
                                'verte' => '🟢 Verte', 'orange' => '🟠 Orange',
                                'rouge' => '🔴 Rouge', default => '—',
                            })
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_relance_1')
                            ->label('1ère relance')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_relance_2')
                            ->label('2ème relance')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_mise_en_demeure')
                            ->label('Mise en demeure')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_resiliation')
                            ->label('Résiliation')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('observations')
                            ->label('Observation')->limit(30)
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),

                // ═══════════════════════════════════════════════════════════
                // 6 TERRAIN 
                // ═══════════════════════════════════════════════════════════
                ColumnGroup::make('6. Terrain')
                    ->columns([
                        TextColumn::make('date_formation_contractants')
                            ->label('Formation contractants')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_suivi_terrain')
                            ->label('Suivi terrain')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('observation_suivi')
                            ->label('Observation suivi')->limit(30)
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),

                // ═══════════════════════════════════════════════════════════
                // 7 RAPPORT TECHNIQUE 
                // ═══════════════════════════════════════════════════════════
                ColumnGroup::make('7. Rapport technique')
                    ->columns([
                        TextColumn::make('date_arrivee_rapport')
                            ->label("Arrivée rapport DEES")->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('evaluateur.name')
                            ->label('Évaluateur')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_transfert_evaluateur')
                            ->label('Transfert évaluateur')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_debut_traitement')
                            ->label('Début traitement')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('reserve_description')
                            ->label('Réserve')->limit(30)
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_envoi_reserve')
                            ->label('Envoi réserve')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_relance_reserve_1')
                            ->label('1ère relance réserve')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_relance_reserve_2')
                            ->label('2ème relance réserve')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('situation_reserves')
                            ->label('Situation réserves')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_validation_evaluateur')
                            ->label('Validation évaluateur')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('date_transmission_daf')
                            ->label('Transmission DAF')->date('d/m/Y')
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('observations_evaluation')
                            ->label('Observations éval.')->limit(30)
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),

                // ═══════════════════════════════════════════════════════════
                // 8 RÉALISATION 
                // ═══════════════════════════════════════════════════════════
                ColumnGroup::make('8. Réalisation')
                    ->columns([
                        TextColumn::make('benef_real_total')
                            ->label('Nb bénéf. formé')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','realise')->first()?->total)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('benef_real_h')
                            ->label('H (réalisé)')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','realise')->first()?->h)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('benef_real_f')
                            ->label('F (réalisé)')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','realise')->first()?->f)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('benef_real_jeunes')
                            ->label('Jeunes (réalisé)')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','realise')->first()?->jeunes)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('benef_real_fpe')
                            ->label('FPE (réalisé)')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','realise')->first()?->fpe)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('benef_real_cadres')
                            ->label('Femmes cadres formées')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','realise')->first()?->cadres)
                            ->numeric()
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('prestataire_realise')
                            ->label('Prestataire (réalisé)')
                            ->getStateUsing(fn (PorteurProj $r) => $r->formations->where('type','realise')->first()?->prestataires?->pluck('nom')?->join(', '))
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('modules_realises')
                            ->label('Modules (réalisé)')->limit(40)
                            ->getStateUsing(fn (PorteurProj $r) => $r->formations->where('type','realise')->first()?->modules?->pluck('intitule')?->join('; '))
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('vol_horaire_par_module_realise')
                            ->label('Vol. h/module (réalisé)')
                            ->getStateUsing(fn (PorteurProj $r) => $r->formations->where('type','realise')->first()?->formMods?->first()?->volume_horaire)
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('formateur_realise')
                            ->label('Formateur (réalisé)')
                            ->getStateUsing(fn (PorteurProj $r) => $r->formations->where('type','realise')->first()?->formateurs?->pluck('nom')?->join(', '))
                            ->toggleable(isToggledHiddenByDefault: true),

                        TextColumn::make('vol_horaire_total_realise')
                            ->label('Vol. h. total (réalisé)')
                            ->getStateUsing(fn (PorteurProj $r) => $r->formations->where('type','realise')->first()?->volume_horaire_total)
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
            ])

            // ═══════════════ FILTRES ═══════════════
            ->filters([
                SelectFilter::make('statut_validation')
                    ->label('Statut')->multiple()
                    ->options([
                        'stand_by'              => 'Stand by',
                        'attente_pieces_regul'  => 'Attente pièces régul.',
                        'validation_financiere' => 'Validation financière',
                        'formation_encours'     => 'Formation en cours',
                        'cloture'               => 'Clôturé',
                        'annule'                => 'Annulé',
                    ]),

                SelectFilter::make('niveau_alerte')
                    ->label('Niveau alerte')->multiple()
                    ->options([
                        'verte'  => '🟢 Verte',
                        'orange' => '🟠 Orange',
                        'rouge'  => '🔴 Rouge',
                    ]),

                SelectFilter::make('guichet')
                    ->label('Guichet')
                    ->relationship('projet.guichet', 'libelle')
                    ->multiple()->preload(),

                SelectFilter::make('vague')
                    ->label('Vague (AP…)')
                    ->relationship('projet.vague', 'code')  // Affiche AP1, AP2, ... au lieu du long libellé
                    ->multiple()
                    ->searchable()
                    ->preload(),

                SelectFilter::make('secteur')
                    ->label('Secteur')
                    ->relationship('projet.secteur', 'libelle')
                    ->multiple()->preload(),

                SelectFilter::make('region')
                    ->label('Région')
                    ->relationship('projet.region', 'libelle')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                SelectFilter::make('porteur_id')
                    ->label('Porteur')
                    ->relationship('porteur', 'raison_sociale')
                    ->searchable()->preload(),

                Filter::make('date_fin')
                    ->label('Période fin')
                    ->schema([
                        DatePicker::make('fin_du')->label('Du'),
                        DatePicker::make('fin_au')->label('Au'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder => $q
                        ->when($data['fin_du'] ?? null, fn ($q, $d) => $q->whereDate('date_fin', '>=', $d))
                        ->when($data['fin_au'] ?? null, fn ($q, $d) => $q->whereDate('date_fin', '<=', $d))),

                Filter::make('montant_total')
                    ->label('Montant total')
                    ->schema([
                        TextInput::make('min')->label('Min (Ar)')->numeric(),
                        TextInput::make('max')->label('Max (Ar)')->numeric(),
                    ])
                    ->query(fn (Builder $q, array $data): Builder => $q
                        ->when($data['min'] ?? null, fn ($q, $m) => $q->where('montant_total', '>=', $m))
                        ->when($data['max'] ?? null, fn ($q, $m) => $q->where('montant_total', '<=', $m))),

                TrashedFilter::make(),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersFormWidth(\Filament\Support\Enums\Width::FourExtraLarge)
            ->deferFilters()
            ->persistFiltersInSession()

            // Clic sur une ligne = ouvre la fiche projet 360° (vue lecture claire)
            ->recordUrl(fn (PorteurProj $r) => PorteurProjResource::getUrl('view', ['record' => $r]))
            ->recordActions([
                ViewAction::make()->label('Voir'),
                EditAction::make()->label('Modifier'),
                \Filament\Actions\Action::make('exporter_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn (PorteurProj $r) => route('export.fiche-projet', ['porteurProj' => $r->id]))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])

            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption($pagination)
            ->defaultSort($triColonne, $triSens)
            ->columnToggleFormColumns(3)
            ->columnToggleFormWidth(\Filament\Support\Enums\Width::SixExtraLarge)
            ->persistColumnSearchesInSession()
            ->emptyStateHeading('Aucun projet trouvé')
            ->emptyStateDescription('Importez un fichier Excel ou créez un nouveau projet pour commencer.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
