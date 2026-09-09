<?php

namespace App\Exports;

use App\Models\PorteurProj;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel des données projets (porteur_proj) avec toutes les colonnes
 * du fichier source IDEE_DE_COLONNES_BASE.xlsx, dans le même ordre.
 */
class ProjetsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    public function __construct(protected array $filters = [])
    {
    }

    public function collection()
    {
        return PorteurProj::with([
            'porteur.region', 'porteur.secteur',
            'projet.statut', 'projet.guichet', 'projet.vague', 'projet.secteur', 'projet.region',
            'partenaires', 'benefs', 'paiements',
            'formations.modules', 'formations.prestataires', 'formations.formateurs',
            'evaluateur',
        ])->get();
    }

    public function headings(): array
    {
        return [
            'Secteur', 'Vague', 'Guichet', 'Référence projet', 'Référence convention',
            'Porteur', 'CNaPS porteur', 'Nb salariés porteur',
            'Partenaire', 'CNaPS partenaire', 'Nb salariés partenaire',
            'Contact', 'Téléphone', 'Adresse', 'Région', 'Intitulé',
            // Bénéficiaires prévus
            'Nb bénéf total (prévu)', 'H (prévu)', 'F (prévu)', 'Jeunes (prévu)', 'FPE (prévu)', 'Femmes cadres (prévu)',
            // Formation prévue
            'Prestataire (prévu)', 'Modules (prévu)', 'Vol. h. par module (prévu)', 'Formateur (prévu)', 'Vol. h. total (prévu)',
            // Financement
            'Montant total', 'Financement demandé', 'DT mobilisé', 'Fonds additionnels', 'Fonds mutualisés', 'Financement (autre)',
            // Contractualisation
            'Appréciation évaluateur', 'Statut', 'Motifs', 'Date notification', 'Date envoi convention', 'Date réception convention',
            'Date début', 'Date fin', 'DANO',
            // Paiements
            'Date paiement J1', 'Montant payé J1', 'Date paiement J2', 'Montant payé J2',
            'Date paiement J3', 'Montant payé J3', 'Allocation consommée', 'Situation',
            // Alertes
            'Situation alerte', 'Date relance 1', 'Date relance 2', 'Date mise en demeure', 'Date résiliation', 'Observation',
            // Suivi
            'Date formation contractants', 'Date suivi terrain', 'Observation suivi terrain',
            // Rapport
            'Date arrivée rapport', 'Évaluateur', 'Date transfert évaluateur', 'Date début traitement',
            'Réserve du projet', 'Date envoi réserve', 'Date 1ère relance réserve', 'Date 2ème relance réserve',
            'Situation des réserves', 'Date validation évaluateur', 'Date transmission DAF', 'Observations évaluation',
            // Bénéficiaires réalisés
            'Nb bénéf formés', 'H (réalisé)', 'F (réalisé)', 'Jeunes (réalisé)', 'FPE (réalisé)', 'Femmes cadres formées',
            // Formation réalisée
            'Prestataire (réalisé)', 'Modules (réalisé)', 'Vol. h. par module (réalisé)', 'Formateur (réalisé)', 'Vol. h. total (réalisé)',
        ];
    }

    public function map($pp): array
    {
        $bp = $pp->benefs->where('type', 'prevu')->first();
        $br = $pp->benefs->where('type', 'realise')->first();
        $fp = $pp->formations->where('type', 'prevu')->first();
        $fr = $pp->formations->where('type', 'realise')->first();

        $pJ1 = $pp->paiements->where('ligne', 'J1')->first();
        $pJ2 = $pp->paiements->where('ligne', 'J2')->first();
        $pJ3 = $pp->paiements->where('ligne', 'J3')->first();

        $statutMap = [
            'incomplet' => 'Incomplet', 'inelig' => 'Inéligible', 'valide' => 'Validé',
            'refuse' => 'Refusé', 'annule' => 'Annulé', 'resilie' => 'Résilié',
        ];
        $situationMap = [
            'non_verse' => 'Non versé', 'partiel' => 'Partiel', 'total' => 'Total',
            'solde' => 'Clôturé', 'annule' => 'Annulé', 'remboursm' => 'Remboursement',
        ];
        $alerteMap = ['verte' => 'Verte', 'orange' => 'Orange', 'rouge' => 'Rouge'];

        return [
            $pp->projet?->secteur?->libelle,
            $pp->projet?->vague?->libelle,
            $pp->projet?->guichet?->libelle,
            $pp->projet?->reference,
            $pp->reference_convention,
            $pp->porteur?->raison_sociale,
            $pp->porteur?->cnaps,
            $pp->porteur?->nb_salaries,
            $pp->partenaires->pluck('nom')->join(', '),
            $pp->partenaires->pluck('cnaps')->filter()->join(', '),
            $pp->partenaires->pluck('nb_salaries')->filter()->join(', '),
            $pp->porteur?->responsable_nom,
            $pp->porteur?->telephone,
            $pp->porteur?->adresse,
            $pp->porteur?->region?->libelle,
            $pp->projet?->intitule,
            // Bénéficiaires prévus
            $bp?->total, $bp?->h, $bp?->f, $bp?->jeunes, $bp?->fpe, $bp?->cadres,
            // Formation prévue
            $fp?->prestataires->pluck('nom')->join(', '),
            $fp?->modules->pluck('intitule')->join('; '),
            $fp?->formMods->first()?->volume_horaire,
            $fp?->formateurs->pluck('nom')->join(', '),
            $fp?->volume_horaire_total,
            // Financement
            $pp->montant_total, $pp->financement_demande, $pp->dt_mobilise,
            $pp->fonds_additionnel, $pp->fonds_mutualise, $pp->financement_autre,
            // Contractualisation
            $pp->appreciation_evaluateur,
            $statutMap[$pp->statut_validation] ?? $pp->statut_validation,
            $pp->motifs,
            $pp->date_notification?->format('d/m/Y'),
            $pp->date_envoi_convention?->format('d/m/Y'),
            $pp->date_reception_convention?->format('d/m/Y'),
            $pp->date_debut?->format('d/m/Y'),
            $pp->date_fin?->format('d/m/Y'),
            $pp->dano_type,
            // Paiements
            $pJ1?->date_paiement?->format('d/m/Y'), $pJ1?->montant,
            $pJ2?->date_paiement?->format('d/m/Y'), $pJ2?->montant,
            $pJ3?->date_paiement?->format('d/m/Y'), $pJ3?->montant,
            $pp->paiements->where('is_annule', false)->sum('montant'),
            $situationMap[$pp->situation_alloc] ?? $pp->situation_alloc,
            // Alertes
            $alerteMap[$pp->niveau_alerte] ?? $pp->niveau_alerte,
            $pp->date_relance_1?->format('d/m/Y'),
            $pp->date_relance_2?->format('d/m/Y'),
            $pp->date_mise_en_demeure?->format('d/m/Y'),
            $pp->date_resiliation?->format('d/m/Y'),
            $pp->observations,
            // Suivi
            $pp->date_formation_contractants?->format('d/m/Y'),
            $pp->date_suivi_terrain?->format('d/m/Y'),
            $pp->observation_suivi,
            // Rapport
            $pp->date_arrivee_rapport?->format('d/m/Y'),
            $pp->evaluateur?->name,
            $pp->date_transfert_evaluateur?->format('d/m/Y'),
            $pp->date_debut_traitement?->format('d/m/Y'),
            $pp->reserve_description,
            $pp->date_envoi_reserve?->format('d/m/Y'),
            $pp->date_relance_reserve_1?->format('d/m/Y'),
            $pp->date_relance_reserve_2?->format('d/m/Y'),
            $pp->situation_reserves,
            $pp->date_validation_evaluateur?->format('d/m/Y'),
            $pp->date_transmission_daf?->format('d/m/Y'),
            $pp->observations_evaluation,
            // Bénéficiaires réalisés
            $br?->total, $br?->h, $br?->f, $br?->jeunes, $br?->fpe, $br?->cadres,
            // Formation réalisée
            $fr?->prestataires->pluck('nom')->join(', '),
            $fr?->modules->pluck('intitule')->join('; '),
            $fr?->formMods->first()?->volume_horaire,
            $fr?->formateurs->pluck('nom')->join(', '),
            $fr?->volume_horaire_total,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('E2');
                $highestColumn = $sheet->getHighestColumn();
                $highestRow    = $sheet->getHighestRow();
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getRowDimension(1)->setRowHeight(30);
            },
        ];
    }
}
