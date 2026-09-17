<?php

namespace App\Imports;

use App\Models\Convention;
use App\Models\Paiement;
use App\Models\PorteurProj;
use App\Models\Projet;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import Excel dédié aux paiements — format WIDE (1 ligne = 1 projet).
 *
 * Colonnes :
 *   A: Référence projet
 *   B: Référence convention
 *   C: Porteur (info)
 *   D: Date paiement J1    | E: Montant payé J1
 *   F: Date paiement J2    | G: Montant payé J2
 *   H: Date paiement J3    | I: Montant payé J3
 *   J: Allocation consommée (facultatif — auto-calculée si vide)
 *   K: Situation (Encours / Clôturée / Stand by / Annulé / Remboursement)
 *
 * Idempotent : re-importer met à jour au lieu de dupliquer (updateOrCreate sur porteur_proj_id + ligne).
 */
class PaiementsExcelImport
{
    public int $importes    = 0;
    public int $misAJour    = 0;
    public int $ignores     = 0;
    public array $rejets    = [];
    public array $doublons  = [];

    /** Mapping des libellés Situation vers les codes de la base */
    private const SITUATION_MAP = [
        'ENCOURS'        => 'partiel',
        'CLOTUREE'       => 'solde',
        'CLOTURE'        => 'solde',
        'STANDBY'        => 'non_verse',
        'ANNULE'         => 'annule',
        'ANNULEE'        => 'annule',
        'REMBOURSEMENT'  => 'remboursm',
    ];

    public function importer(string $chemin): void
    {
        try {
            $sp = IOFactory::load($chemin);
        } catch (\Throwable $e) {
            $this->rejets[] = "Impossible d'ouvrir le fichier : " . $e->getMessage();
            return;
        }

        // Chercher la feuille "Paiements" ou prendre la 1ère
        $sh = null;
        foreach ($sp->getAllSheets() as $sheet) {
            if (mb_strtolower(trim($sheet->getTitle())) === 'paiements') {
                $sh = $sheet;
                break;
            }
        }
        $sh ??= $sp->getActiveSheet();

        // 2e paramètre à true : les classeurs de la DEES calculent certains
        // totaux par formule. Sans évaluation, on lirait le TEXTE de la
        // formule — « =SUM(Tableau23[[#This Row],[Homme]]...) » — dont on
        // extrayait par erreur les chiffres du nom de table.
        $lignes = $sh->toArray(null, true, false, false);

        // Données à partir de la ligne 4 (index 3) — lignes 1-3 = en-têtes
        for ($i = 3; $i < count($lignes); $i++) {
            $numLigne = $i + 1;
            $row = $lignes[$i];

            $refProjet = trim((string) ($row[0] ?? ''));
            $refConv   = trim((string) ($row[1] ?? ''));
            // $porteur = $row[2] — info seulement, non utilisée

            $dates = [
                'J1' => $row[3] ?? null,
                'J2' => $row[5] ?? null,
                'J3' => $row[7] ?? null,
            ];
            $montants = [
                'J1' => $this->parseMontant($row[4] ?? null),
                'J2' => $this->parseMontant($row[6] ?? null),
                'J3' => $this->parseMontant($row[8] ?? null),
            ];

            $situationTxt = trim((string) ($row[10] ?? ''));

            // Skip ligne bannière ou exemples
            if (str_contains(mb_strtolower($refProjet), 'supprimez')) continue;
            if (str_contains(mb_strtolower($refProjet), 'exemple')) continue;

            // Skip ligne vide
            $auMoinsUneTranche = collect($montants)->filter()->isNotEmpty()
                || collect($dates)->filter(fn ($d) => filled($d))->isNotEmpty();
            if (blank($refProjet) && blank($refConv) && ! $auMoinsUneTranche) continue;

            // ─── Recherche du porteur_proj (stratégie A : ref projet OU ref conv) ───
            $porteurProj = $this->trouverPorteurProj($refProjet, $refConv);

            if ($porteurProj === null) {
                $ref = filled($refProjet) ? $refProjet : $refConv;
                $this->rejets[] = "Ligne {$numLigne} : projet introuvable (« {$ref} »)";
                $this->ignores++;
                continue;
            }

            // ─── Créer/mettre à jour chaque tranche remplie ───
            $nbTranchesTraitees = 0;
            foreach (['J1', 'J2', 'J3'] as $tranche) {
                $date = $this->parseDate($dates[$tranche]);
                $montant = $montants[$tranche];

                if (! $date || ! $montant || $montant <= 0) continue;

                $existant = Paiement::where('porteur_proj_id', $porteurProj->id)
                    ->where('ligne', $tranche)
                    ->first();

                $donnees = [
                    'porteur_proj_id' => $porteurProj->id,
                    'ligne'           => $tranche,
                    'date_paiement'   => $date,
                    'montant'         => $montant,
                    'is_annule'       => false,
                    'updated_by'      => auth()->id(),
                ];

                if ($existant) {
                    $existant->fill($donnees)->save();
                    $this->doublons[] = "Ligne {$numLigne}, {$tranche} : paiement existant mis à jour";
                    $this->misAJour++;
                } else {
                    $donnees['created_by'] = auth()->id();
                    Paiement::create($donnees);
                    $this->importes++;
                }
                $nbTranchesTraitees++;
            }

            // ─── Mettre à jour la situation du porteur_proj si fournie ───
            if (filled($situationTxt)) {
                $situationCode = $this->normaliserSituation($situationTxt);
                if ($situationCode) {
                    $porteurProj->update(['situation_alloc' => $situationCode]);
                }
            }

            // Aucune tranche traitée mais projet trouvé → note
            if ($nbTranchesTraitees === 0 && blank($situationTxt)) {
                $this->rejets[] = "Ligne {$numLigne} : aucune tranche renseignée (Date + Montant vides)";
                $this->ignores++;
            }
        }
    }

    /**
     * Trouve un PorteurProj à partir d'une ref projet ou ref convention (matching souple).
     */
    protected function trouverPorteurProj(string $refProjet, string $refConv): ?PorteurProj
    {
        $refProjetNorm = Projet::normaliserReference($refProjet);
        $refConvNorm   = PorteurProj::normaliserReference($refConv);

        // 1) via projet.reference_normalisee
        if ($refProjetNorm !== null) {
            $projet = Projet::where('reference_normalisee', $refProjetNorm)->first();
            if ($projet) {
                $pp = PorteurProj::where('projet_id', $projet->id)->first();
                if ($pp) return $pp;
            }
        }

        // 2) via porteur_proj.reference_convention_normalisee
        if ($refConvNorm !== null) {
            $pp = PorteurProj::where('reference_convention_normalisee', $refConvNorm)->first();
            if ($pp) return $pp;
        }

        // 3) via convention.reference_normalisee → projet → porteur_proj
        if ($refConvNorm !== null) {
            $conv = Convention::where('reference_normalisee', $refConvNorm)->first();
            if ($conv) {
                $projet = Projet::where('convention_id', $conv->id)->first();
                if ($projet) {
                    $pp = PorteurProj::where('projet_id', $projet->id)->first();
                    if ($pp) return $pp;
                }
            }
        }

        return null;
    }

    /**
     * Normalise la situation saisie (avec accents, casse) vers le code base.
     */
    protected function normaliserSituation(?string $texte): ?string
    {
        if (blank($texte)) return null;
        $norm = mb_strtoupper(trim($texte));
        // Retirer accents et caractères spéciaux
        $norm = preg_replace('/[\s\-_\/\.]+/u', '', $norm);
        $norm = strtr($norm, [
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'À' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'Î' => 'I', 'Ï' => 'I',
            'Ô' => 'O', 'Ö' => 'O',
            'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C',
        ]);
        return self::SITUATION_MAP[$norm] ?? null;
    }

    /**
     * Parse une date au format JJ/MM/AAAA, AAAA-MM-JJ, ou nombre Excel.
     */
    protected function parseDate($valeur): ?string
    {
        if (blank($valeur)) return null;

        if (is_numeric($valeur)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $valeur)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $valeur = trim((string) $valeur);
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $valeur);
            if ($dt !== false) return $dt->format('Y-m-d');
        }
        return null;
    }

    protected function parseMontant($valeur): ?float
    {
        if (blank($valeur)) return null;
        if (is_numeric($valeur)) return (float) $valeur;
        $nettoye = preg_replace('/[^\d.,]/', '', (string) $valeur);
        $nettoye = str_replace([' ', ','], ['', '.'], $nettoye);
        return is_numeric($nettoye) ? (float) $nettoye : null;
    }
}
