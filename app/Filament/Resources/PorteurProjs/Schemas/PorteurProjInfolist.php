<?php

namespace App\Filament\Resources\PorteurProjs\Schemas;

use App\Models\PorteurProj;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Fiche projet 360° — vue lecture en 8 cartes colorées.
 *
 * Reprend exactement les 8 sections du fichier Excel DEES :
 *   1. 🔵 Information générale
 *   2. 🟢 Prévisionnel
 *   3. ⚪ Contractualisation & mise en œuvre
 *   4. ⚫ Paiement (DAF)
 *   5. 🟡 Alerte rouge
 *   6. 🟠 Terrain
 *   7. 🟣 Rapport technique
 *   8. 🟤 Réalisation
 */
class PorteurProjInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // ═══════════════ EN-TÊTE — Résumé du projet ═══════════════
            Section::make()
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    TextEntry::make('projet.reference')
                        ->label('Référence projet')
                        ->weight('bold')
                        ->size('lg')
                        ->copyable(),

                    TextEntry::make('porteur.raison_sociale')
                        ->label('Porteur')
                        ->weight('bold')
                        ->size('lg'),

                    TextEntry::make('statut_validation')
                        ->label('Statut')
                        ->badge()
                        ->size('lg')
                        ->color(fn (?string $s) => match ($s) {
                            'stand_by'              => 'gray',
                            'attente_pieces_regul'  => 'warning',
                            'validation_financiere' => 'info',
                            'formation_encours'     => 'primary',
                            'cloture'               => 'success',
                            'annule'                => 'danger',
                            default                 => 'gray',
                        })
                        ->formatStateUsing(fn (?string $s, PorteurProj $r) =>
                            $r->projet?->statut?->libelle ?? ucfirst(str_replace('_', ' ', $s ?? '—'))
                        ),

                    TextEntry::make('niveau_alerte')
                        ->label('Niveau alerte')
                        ->badge()
                        ->size('lg')
                        ->color(fn (?string $s) => match ($s) {
                            'rouge' => 'danger', 'orange' => 'warning', default => 'success',
                        })
                        ->formatStateUsing(fn (?string $s) => match ($s) {
                            'verte' => '🟢 Verte', 'orange' => '🟠 Orange',
                            'rouge' => '🔴 Rouge', default => '—',
                        }),
                ]),

            // ═══════════════ 1️⃣ INFORMATION GÉNÉRALE (bleu) ═══════════════
            Section::make('1. Information générale')
                ->icon('heroicon-o-information-circle')
                ->collapsible()
                ->columnSpan(1)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('projet.secteur.libelle')->label('Secteur')->badge(),
                        TextEntry::make('projet.vague.libelle')->label('Vague')->badge(),
                        TextEntry::make('projet.guichet.libelle')->label('Guichet')->badge(),
                        TextEntry::make('reference_convention')->label('Réf. convention')->copyable(),
                        TextEntry::make('porteur.cnaps')->label('CNaPS porteur'),
                        TextEntry::make('porteur.nb_salaries')->label('Nb salariés')->numeric(),
                    ]),
                    TextEntry::make('projet.intitule')
                        ->label('Intitulé du projet')
                        ->columnSpanFull()
                        ->prose(),
                    Grid::make(2)->schema([
                        TextEntry::make('porteur.responsable_nom')->label('Contact'),
                        TextEntry::make('porteur.telephone')->label('Téléphone')->copyable(),
                        TextEntry::make('porteur.email')->label('Email')->copyable(),
                        TextEntry::make('porteur.region.libelle')->label('Région'),
                    ]),
                    TextEntry::make('porteur.adresse')->label('Adresse')->columnSpanFull(),
                    // ─── Mini-tableau des partenaires ───
                    TextEntry::make('partenaires_titre')
                        ->label('')
                        ->getStateUsing(fn (PorteurProj $r) =>
                            $r->partenaires->isEmpty()
                                ? '👥 Aucun partenaire enregistré'
                                : '👥 Partenaires (' . $r->partenaires->count() . ')'
                        )
                        ->weight('bold')
                        ->columnSpanFull(),

                    RepeatableEntry::make('partenaires')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->visible(fn (PorteurProj $r) => $r->partenaires->isNotEmpty())
                        ->columns(3)
                        ->schema([
                            TextEntry::make('nom')
                                ->label('Nom')
                                ->weight('bold'),
                            TextEntry::make('cnaps')
                                ->label('CNaPS')
                                ->placeholder('—'),
                            TextEntry::make('nb_salaries')
                                ->label('Nb salariés')
                                ->numeric()
                                ->placeholder('—'),
                        ]),
                ]),

            // ═══════════════ 2️⃣ PRÉVISIONNEL (vert) ═══════════════
            Section::make('2. Prévisionnel')
                ->icon('heroicon-o-clipboard-document-check')
                ->collapsible()
                ->columnSpan(1)
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('benef_prev_total')->label('Nb bénéf. total')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','prevu')->first()?->total ?? 0)
                            ->numeric()->badge()->color('success'),
                        TextEntry::make('benef_prev_h')->label('Hommes')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','prevu')->first()?->h ?? 0)
                            ->numeric(),
                        TextEntry::make('benef_prev_f')->label('Femmes')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','prevu')->first()?->f ?? 0)
                            ->numeric(),
                        TextEntry::make('benef_prev_jeunes')->label('Jeunes')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','prevu')->first()?->jeunes ?? 0)
                            ->numeric(),
                        TextEntry::make('benef_prev_fpe')->label('FPE')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','prevu')->first()?->fpe ?? 0)
                            ->numeric(),
                        TextEntry::make('benef_prev_cadres')->label('Femmes cadres')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','prevu')->first()?->cadres ?? 0)
                            ->numeric(),
                    ]),
                    TextEntry::make('formation_prevu_prestataire')
                        ->label('Prestataire prévu')
                        ->getStateUsing(fn (PorteurProj $r) =>
                            $r->formations->where('type','prevu')->first()?->prestataires?->pluck('nom')?->join(', ') ?: '—'
                        )
                        ->columnSpanFull(),
                    TextEntry::make('formation_prevu_modules')
                        ->label('Modules prévus')
                        ->getStateUsing(fn (PorteurProj $r) =>
                            $r->formations->where('type','prevu')->first()?->modules?->pluck('intitule')?->join(' ; ') ?: '—'
                        )
                        ->columnSpanFull(),
                    Grid::make(2)->schema([
                        TextEntry::make('formation_prevu_formateur')
                            ->label('Formateur(s)')
                            ->getStateUsing(fn (PorteurProj $r) =>
                                $r->formations->where('type','prevu')->first()?->formateurs?->pluck('nom')?->join(', ') ?: '—'
                            ),
                        TextEntry::make('formation_prevu_volume')
                            ->label('Volume horaire total')
                            ->getStateUsing(fn (PorteurProj $r) =>
                                $r->formations->where('type','prevu')->first()?->volume_horaire_total ?? 0
                            )
                            ->suffix(' h'),
                    ]),
                ]),

            // ═══════════════ 3️⃣ CONTRACTUALISATION (gris) ═══════════════
            Section::make('3. Contractualisation & mise en œuvre')
                ->icon('heroicon-o-document-text')
                ->collapsible()
                ->columnSpan(1)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('montant_total')->label('Montant total')
                            ->numeric()->suffix(' Ar')->weight('bold'),
                        TextEntry::make('financement_demande')->label('Financement demandé')
                            ->numeric()->suffix(' Ar'),
                        TextEntry::make('dt_mobilise')->label('DT mobilisé')
                            ->numeric()->suffix(' Ar'),
                        TextEntry::make('fonds_additionnel')->label('Fonds additionnels')
                            ->numeric()->suffix(' Ar'),
                        TextEntry::make('fonds_mutualise')->label('Fonds mutualisé')
                            ->numeric()->suffix(' Ar'),
                        TextEntry::make('financement_autre')->label('Autre financement')
                            ->numeric()->suffix(' Ar'),
                    ]),
                    TextEntry::make('appreciation_evaluateur')->label('Appréciation évaluateur')
                        ->columnSpanFull()->placeholder('—'),
                    TextEntry::make('motifs')->label('Motifs')
                        ->columnSpanFull()->placeholder('—'),
                    Grid::make(2)->schema([
                        TextEntry::make('date_notification')->label('Date notification')->date('d/m/Y'),
                        TextEntry::make('date_envoi_convention')->label("Date d'envoi convention")->date('d/m/Y'),
                        TextEntry::make('date_reception_convention')->label('Date réception convention')->date('d/m/Y'),
                        TextEntry::make('date_debut')->label('Date début')->date('d/m/Y'),
                        TextEntry::make('date_fin')->label('Date fin')->date('d/m/Y'),
                        TextEntry::make('dano_type')->label('DANO')->badge()->color('warning')->placeholder('—'),
                    ]),
                ]),

            // ═══════════════ 4️⃣ PAIEMENT (DAF) ═══════════════
            Section::make('4. Paiement (DAF)')
                ->icon('heroicon-o-banknotes')
                ->collapsible()
                ->columnSpan(1)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('paiement_j1_date')
                            ->label('Date J1')
                            ->getStateUsing(fn (PorteurProj $r) =>
                                optional($r->paiements->where('ligne','J1')->first()?->date_paiement)?->format('d/m/Y') ?? '—'
                            ),
                        TextEntry::make('paiement_j1_montant')
                            ->label('Montant J1')
                            ->getStateUsing(fn (PorteurProj $r) => $r->paiements->where('ligne','J1')->first()?->montant ?? 0)
                            ->numeric()->suffix(' Ar'),

                        TextEntry::make('paiement_j2_date')
                            ->label('Date J2')
                            ->getStateUsing(fn (PorteurProj $r) =>
                                optional($r->paiements->where('ligne','J2')->first()?->date_paiement)?->format('d/m/Y') ?? '—'
                            ),
                        TextEntry::make('paiement_j2_montant')
                            ->label('Montant J2')
                            ->getStateUsing(fn (PorteurProj $r) => $r->paiements->where('ligne','J2')->first()?->montant ?? 0)
                            ->numeric()->suffix(' Ar'),

                        TextEntry::make('paiement_j3_date')
                            ->label('Date J3')
                            ->getStateUsing(fn (PorteurProj $r) =>
                                optional($r->paiements->where('ligne','J3')->first()?->date_paiement)?->format('d/m/Y') ?? '—'
                            ),
                        TextEntry::make('paiement_j3_montant')
                            ->label('Montant J3')
                            ->getStateUsing(fn (PorteurProj $r) => $r->paiements->where('ligne','J3')->first()?->montant ?? 0)
                            ->numeric()->suffix(' Ar'),
                    ]),
                    TextEntry::make('allocation_consommee')
                        ->label('Allocation consommée (J1+J2+J3)')
                        ->getStateUsing(fn (PorteurProj $r) => $r->paiements->where('is_annule', false)->sum('montant'))
                        ->numeric()->suffix(' Ar')->weight('bold')->size('lg'),
                    TextEntry::make('situation_alloc')
                        ->label('Situation')->badge(),
                ]),

            // ═══════════════ 5️⃣ ALERTE ROUGE (jaune) ═══════════════
            Section::make('5. Alerte rouge & Relances')
                ->icon('heroicon-o-exclamation-triangle')
                ->collapsible()
                ->columnSpan(1)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('date_relance_1')->label('1ère relance')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('date_relance_2')->label('2ème relance')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('date_mise_en_demeure')->label('Mise en demeure')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('date_resiliation')->label('Résiliation')->date('d/m/Y')->placeholder('—'),
                    ]),
                    TextEntry::make('observations')->label('Observation')->columnSpanFull()->placeholder('—'),
                ]),

            // ═══════════════ 6️⃣ TERRAIN (orange) ═══════════════
            Section::make('6. Suivi terrain')
                ->icon('heroicon-o-map-pin')
                ->collapsible()
                ->columnSpan(1)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('date_formation_contractants')->label('Formation contractants')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('date_suivi_terrain')->label('Date suivi terrain')->date('d/m/Y')->placeholder('—'),
                    ]),
                    TextEntry::make('observation_suivi')->label('Observation suivi')->columnSpanFull()->placeholder('—'),
                ]),

            // ═══════════════ 7️⃣ RAPPORT TECHNIQUE (violet) ═══════════════
            Section::make('7. Rapport technique & Évaluation')
                ->icon('heroicon-o-document-chart-bar')
                ->collapsible()
                ->columnSpan(1)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('date_arrivee_rapport')->label('Arrivée rapport DEES')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('evaluateur.name')->label('Évaluateur')->placeholder('—'),
                        TextEntry::make('date_transfert_evaluateur')->label('Transfert évaluateur')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('date_debut_traitement')->label('Début traitement')->date('d/m/Y')->placeholder('—'),
                    ]),
                    TextEntry::make('reserve_description')->label('Réserve du projet')->columnSpanFull()->placeholder('—'),
                    Grid::make(2)->schema([
                        TextEntry::make('date_envoi_reserve')->label('Envoi réserve')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('situation_reserves')->label('Situation réserves')->placeholder('—'),
                        TextEntry::make('date_relance_reserve_1')->label('1ère relance réserve')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('date_relance_reserve_2')->label('2ème relance réserve')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('date_validation_evaluateur')->label('Validation évaluateur')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('date_transmission_daf')->label('Transmission DAF')->date('d/m/Y')->placeholder('—'),
                    ]),
                    TextEntry::make('observations_evaluation')->label('Observations éval.')->columnSpanFull()->placeholder('—'),
                ]),

            // ═══════════════ 8️⃣ RÉALISATION (marron) ═══════════════
            Section::make('8. Réalisation')
                ->icon('heroicon-o-check-badge')
                ->collapsible()
                ->columnSpan(1)
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('benef_real_total')->label('Nb bénéf. formé')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','realise')->first()?->total ?? 0)
                            ->numeric()->badge()->color('success'),
                        TextEntry::make('benef_real_h')->label('Hommes')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','realise')->first()?->h ?? 0)
                            ->numeric(),
                        TextEntry::make('benef_real_f')->label('Femmes')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','realise')->first()?->f ?? 0)
                            ->numeric(),
                        TextEntry::make('benef_real_jeunes')->label('Jeunes')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','realise')->first()?->jeunes ?? 0)
                            ->numeric(),
                        TextEntry::make('benef_real_fpe')->label('FPE')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','realise')->first()?->fpe ?? 0)
                            ->numeric(),
                        TextEntry::make('benef_real_cadres')->label('Femmes cadres formées')
                            ->getStateUsing(fn (PorteurProj $r) => $r->benefs->where('type','realise')->first()?->cadres ?? 0)
                            ->numeric(),
                    ]),
                    TextEntry::make('formation_real_prestataire')
                        ->label('Prestataire réel')
                        ->getStateUsing(fn (PorteurProj $r) =>
                            $r->formations->where('type','realise')->first()?->prestataires?->pluck('nom')?->join(', ') ?: '—'
                        )
                        ->columnSpanFull(),
                    TextEntry::make('formation_real_modules')
                        ->label('Modules réels')
                        ->getStateUsing(fn (PorteurProj $r) =>
                            $r->formations->where('type','realise')->first()?->modules?->pluck('intitule')?->join(' ; ') ?: '—'
                        )
                        ->columnSpanFull(),
                    TextEntry::make('formation_real_volume')
                        ->label('Volume horaire réalisé')
                        ->getStateUsing(fn (PorteurProj $r) =>
                            $r->formations->where('type','realise')->first()?->volume_horaire_total ?? 0
                        )
                        ->suffix(' h'),
                ]),
        ])->columns(2);
    }
}
