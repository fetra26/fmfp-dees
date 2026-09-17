<?php

namespace App\Imports;

use App\Models\Benef;
use App\Models\Convention;
use App\Models\Formation;
use App\Models\FormMod;
use App\Models\Formateur;
use App\Models\Module;
use App\Models\Paiement;
use App\Models\Partenaire;
use App\Models\Porteur;
use App\Models\PorteurProj;
use App\Models\PrestaForm;
use App\Models\Prestataire;
use App\Models\Projet;
use App\Models\Region;
use App\Models\Secteur;
use App\Models\StatutProjet;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Importeur Excel par INDEX DE COLONNE (A=0, B=1, ..., CD=81).
 *
 * Pourquoi par index et pas par en-tête : le fichier DEES contient des en-têtes
 * dupliqués (H, F, Jeunes, FPE apparaissent dans la section prévu ET réalisé).
 * Maatwebsite/Excel écrase les doublons, ce qui mélange prévu et réalisé.
 */
class ProjetExcelImport implements ToCollection, WithStartRow, WithChunkReading, WithCalculatedFormulas
{
    public int $imported = 0;
    public int $skipped  = 0;
    public array $errors = [];

    /** Statistiques de la feuille "Partenaires" (nouveau template v3) */
    public int $partenairesImportes    = 0;
    public int $partenairesNonAssocies = 0;
    public array $partenairesRejets    = [];
    public array $doublonsProjets      = [];

    protected int $startRowValue = 2;

    // Format détecté : 'idee' (IDEE_DE_COLONNES_BASE) ou 'dees_bdd' (DEES_BDD_VF)
    protected string $formatDetecte = 'idee';

    /**
     * Cache en mémoire des référentiels résolus.
     * Évite des milliers de SELECT redondants sur 2000+ lignes.
     */
    protected array $cacheGuichets = [];
    protected array $cacheVagues = [];
    protected array $cacheSecteurs = [];

    /** L'index des secteurs officiels n'est construit qu'une fois par import. */
    protected bool $cacheSecteursCharge = false;

    /**
     * Libellés de secteur rencontrés dans le fichier sans correspondance dans
     * la nomenclature FMFP. Listés une seule fois chacun dans le rapport
     * d'import, pour que la DEES sache quoi rattacher.
     *
     * @var string[]
     */
    public array $secteursInconnus = [];
    protected array $cacheRegions = [];
    protected array $cacheStatuts = [];
    protected array $cachePorteurs = [];

    public function startRow(): int { return $this->startRowValue; }

    /**
     * Lecture par chunks de 500 lignes pour économiser la mémoire.
     * Crucial pour les gros fichiers (2000+ lignes).
     */
    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * @param int $headingRow  Position des en-têtes (informatif uniquement)
     * @param int $startRow    Première ligne de données
     * @param string $format   'idee' ou 'dees_bdd'
     */
    public function setFormat(int $headingRow, int $startRow, string $format = 'idee'): void
    {
        $this->startRowValue = $startRow;
        $this->formatDetecte = $format;
    }

    /** Compteur global de lignes traitées — évite la collision entre chunks
     *  (dans WithChunkReading, $index dans collection() redémarre à 0 à chaque chunk). */
    protected int $ligneGlobale = 0;

    public function collection(Collection $rows): void
    {
        // Au 1er appel : positionner sur la 1ère ligne de données
        if ($this->ligneGlobale === 0) {
            $this->ligneGlobale = $this->startRow();
        }

        foreach ($rows as $row) {
            $numLigne = $this->ligneGlobale++;
            try {
                if ($this->formatDetecte === 'dees_bdd') {
                    $this->traiterLigneFormatDeesBdd($row->toArray(), $numLigne);
                } else {
                    $this->traiterLigne($row->toArray(), $numLigne);
                }
            } catch (\Throwable $e) {
                $this->skipped++;
                $this->errors[] = "Ligne {$numLigne} : " . $e->getMessage();
                Log::warning("Import ligne {$numLigne} : " . $e->getMessage());
            }
        }
    }

    // =========================================================
    // Helper : récupérer une cellule par lettre de colonne Excel
    // =========================================================
    protected function cell(array $row, string $col): mixed
    {
        $idx = 0;
        $col = strtoupper(trim($col));
        for ($i = 0; $i < strlen($col); $i++) {
            $idx = $idx * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        $idx--; // 0-based

        return $row[$idx] ?? null;
    }

    // =========================================================
    // Traitement d'une ligne
    // =========================================================

    protected function traiterLigne(array $row, int $numLigne): void
    {
        // ─── Mapping des colonnes (aligné TEMPLATE_IMPORT_DEES actuel) ───
        // A=Secteur B=Vague C=Guichet D=RefProj E=RefConv F=Porteur G=CNaPS H=NbSal
        // I=NbPartenaires(info) J=Contact K=Tel L=Email M=Adresse N=Région O=Intitulé
        // P=NbBénéfTotal Q=H R=F S=Jeunes T=FPE U=FCadres
        // V=Prestataire W=Modules X=Vol/mod Y=Formateur Z=VolTotal
        // AA=Mnt tot AB=FinDem AC=DTMob AD=FondsAdd AE=FondsMut AF=FinAutre
        // AG=Apprécial AH=Statut AI=Motifs AJ=DateNotif AK=DateEnvConv AL=DateRecConv AM=DateDéb AN=DateFin AO=DANO
        // AP=DateJ1 AQ=MntJ1 AR=DateJ2 AS=MntJ2 AT=DateJ3 AU=MntJ3 AV=AllocCons AW=Situation
        // AX=AlerteNiv AY=DateRel1 AZ=DateRel2 BA=DateMED BB=DateRésil BC=Observation
        // BD=DateFormContract BE=DateSuivi BF=ObsSuivi

        $nomPorteur = $this->normaliserNom((string) $this->cell($row, 'F'));   // col F
        $intitule   = trim((string) $this->cell($row, 'O'));                    // col O (Intitulé)
        $refProjet  = trim((string) $this->cell($row, 'D'));                    // col D

        // ── Une ligne n'est importable que si elle IDENTIFIE un projet ──
        //
        // Quatre colonnes font identité : référence projet (D), référence
        // convention (E), porteur (F) et intitulé (O). Sans aucune des quatre,
        // il n'y a rien à importer, et la ligne est ignorée en silence.
        //
        // Le secteur (A) ne compte volontairement PAS comme identité : les bas
        // de tableau Excel portent souvent une valeur résiduelle dans une
        // colonne de référentiel, sans le moindre projet derrière. L'inclure
        // fabriquait des projets fantômes nommés A_REMPLIR_L<n> qu'il fallait
        // ensuite retrouver et supprimer à la main.
        $identifiants = [
            $refProjet,
            trim((string) $this->cell($row, 'E')),
            $nomPorteur,
            $intitule,
        ];

        if (collect($identifiants)->every(fn ($valeur) => blank($valeur))) {
            $this->skipped++;
            return;
        }

        // Règle : on importe TOUTES les lignes non vides.
        // Réf projet manquante :
        //   - Si convention (col E) présente → reference = « CONV_ » + refConv (parlante)
        //   - Sinon → « A_REMPLIR_L<n> » pour repérage manuel
        if (blank($refProjet)) {
            $refConvCell = trim((string) $this->cell($row, 'E'));
            if (filled($refConvCell)) {
                // Reference col est VARCHAR(100) → truncate au besoin
                $refProjet = Str::limit('CONV_' . $refConvCell, 100, '');
                $this->errors[] = "Ligne {$numLigne} : réf projet vide → « {$refProjet} » (dérivée de la convention)";
            } else {
                $refProjet = "A_REMPLIR_L{$numLigne}";
                $this->errors[] = "Ligne {$numLigne} : ni réf projet ni convention → « {$refProjet} » (à compléter)";
            }
        } else {
            // Aussi truncate le refProjet Excel si trop long
            $refProjet = Str::limit($refProjet, 100, '');
        }

        if (blank($nomPorteur)) {
            $nomPorteur = "A_REMPLIR_L{$numLigne}";
            $this->errors[] = "Ligne {$numLigne} : porteur vide → « {$nomPorteur} » (à compléter)";
        }

        // ── 1. PORTEUR ──────────────────────────────────────────
        $porteur = $this->resoudrePorteur($row, $nomPorteur, $numLigne);

        // ── 2. PROJET ───────────────────────────────────────────
        // Règle : merge-conservative + log conflits.
        // - Champ vide en DB, rempli Excel → REMPLIR
        // - Champ rempli en DB, vide Excel → GARDER DB (jamais écraser par vide)
        // - Champs remplis mais différents → GARDER DB + LOG conflit (à trancher plus tard)
        $refNormalisee = Projet::normaliserReference($refProjet);

        $projet = Projet::where('reference_normalisee', $refNormalisee)->first()
            ?? new Projet();

        if ($projet->exists) {
            $this->doublonsProjets[] = "Ligne {$numLigne} : « {$refProjet} » = « {$projet->reference} » (fusion automatique)";
        }

        // Valeurs Excel candidats
        $statutTxt = trim((string) $this->cell($row, 'AH'));
        $candidats = [
            'reference'        => $refProjet,
            'intitule'         => blank($intitule) ? null : Str::limit($intitule, 500),
            'statut_projet_id' => blank($statutTxt) ? null : $this->resoudreStatut($statutTxt)?->id,
            'guichet_id'       => $this->resoudreGuichet((string) $this->cell($row, 'C'))?->id,
            'vague_id'         => $this->resoudreVague((string) $this->cell($row, 'B'))?->id,
            'secteur_id'       => $this->resoudreSecteur((string) $this->cell($row, 'A'))?->id,
            'region_id'        => $this->resoudreRegion((string) $this->cell($row, 'N'))?->id,
            'date_debut'       => $this->parseDate((string) $this->cell($row, 'AM')),
            'date_fin'         => $this->parseDate((string) $this->cell($row, 'AN')),
        ];

        if (! $projet->exists) {
            // Nouveau projet : on remplit tout
            foreach ($candidats as $col => $val) {
                $projet->$col = $val;
            }
            // intitulé NOT NULL en base → '' si vide
            if (blank($projet->intitule)) $projet->intitule = '';
            $projet->created_by = auth()->id();
        } else {
            // Projet existant : merge-conservative
            $this->mergeConservative($projet, $candidats, $numLigne);
        }
        $projet->save();

        // ── 3. CONVENTION ────────────────────────────────────────
        $refConv = trim((string) $this->cell($row, 'E'));                       // col E
        if (filled($refConv) && ! $projet->convention_id) {
            $conv = Convention::firstOrCreate(
                ['reference' => $refConv],
                [
                    'date_signature' => $this->parseDate((string) $this->cell($row, 'AK')),
                    'date_effet'     => $this->parseDate((string) $this->cell($row, 'AL')),
                    'created_by'     => auth()->id(),
                ]
            );
            $projet->update(['convention_id' => $conv->id]);
        }

        // ── 4. PORTEUR_PROJ ──────────────────────────────────────
        // Clé composite : (projet_id + porteur_id + reference_convention_normalisee)
        // → une même conv+projet+porteur = 1 seule entrée
        // → même projet, porteurs différents = plusieurs entrées (multi-porteurs légitime)
        $refConvNorm = filled($refConv) ? \App\Models\ImportMapping::normaliser($refConv) : null;

        $porteurProj = PorteurProj::where([
            'projet_id'  => $projet->id,
            'porteur_id' => $porteur->id,
        ])->where(function ($q) use ($refConvNorm) {
            if ($refConvNorm === null) $q->whereNull('reference_convention_normalisee');
            else $q->where('reference_convention_normalisee', $refConvNorm);
        })->first() ?? new PorteurProj();

        // Valeurs candidates (Excel)
        $candidatsPP = [
            'reference_convention'        => $refConv ?: null,
            'reference_convention_normalisee' => $refConvNorm,

            'montant_total'               => $this->parseMontant($this->cell($row, 'AA')),
            'financement_demande'         => $this->parseMontant($this->cell($row, 'AB')),
            'dt_mobilise'                 => $this->parseMontant($this->cell($row, 'AC')),
            'fonds_additionnel'           => $this->parseMontant($this->cell($row, 'AD')),
            'fonds_mutualise'             => $this->parseMontant($this->cell($row, 'AE')),
            'financement_autre'           => $this->parseMontant($this->cell($row, 'AF')),

            'appreciation_evaluateur'     => trim((string) $this->cell($row, 'AG')) ?: null,
            'statut_validation'           => $this->normaliserStatutValidation((string) $this->cell($row, 'AH')),
            'motifs'                      => trim((string) $this->cell($row, 'AI')) ?: null,

            'date_notification'           => $this->parseDate((string) $this->cell($row, 'AJ')),
            'date_envoi_convention'       => $this->parseDate((string) $this->cell($row, 'AK')),
            'date_reception_convention'   => $this->parseDate((string) $this->cell($row, 'AL')),
            'date_debut'                  => $this->parseDate((string) $this->cell($row, 'AM')),
            'date_fin'                    => $this->parseDate((string) $this->cell($row, 'AN')),

            'dano_type'                   => trim((string) $this->cell($row, 'AO')) ?: null,

            'situation_alloc'             => $this->normaliserSituationAlloc((string) $this->cell($row, 'AW')),

            'niveau_alerte'               => $this->normaliserAlerte((string) $this->cell($row, 'AX')),
            'date_relance_1'              => $this->parseDate((string) $this->cell($row, 'AY')),
            'date_relance_2'              => $this->parseDate((string) $this->cell($row, 'AZ')),
            'date_mise_en_demeure'        => $this->parseDate((string) $this->cell($row, 'BA')),
            'date_resiliation'            => $this->parseDate((string) $this->cell($row, 'BB')),
            'observations'                => trim((string) $this->cell($row, 'BC')) ?: null,

            'date_formation_contractants' => $this->parseDate((string) $this->cell($row, 'BD')),
            'date_suivi_terrain'          => $this->parseDate((string) $this->cell($row, 'BE')),
            'observation_suivi'           => trim((string) $this->cell($row, 'BF')) ?: null,

            // ─── Section 7 : RAPPORT TECHNIQUE (BG–BR) ───
            'date_arrivee_rapport'         => $this->parseDate((string) $this->cell($row, 'BG')),
            'evaluateur_id'                => $this->resoudreEvaluateur((string) $this->cell($row, 'BH')),
            'date_transfert_evaluateur'    => $this->parseDate((string) $this->cell($row, 'BI')),
            'date_debut_traitement'        => $this->parseDate((string) $this->cell($row, 'BJ')),
            'reserve_description'          => trim((string) $this->cell($row, 'BK')) ?: null,
            'date_envoi_reserve'           => $this->parseDate((string) $this->cell($row, 'BL')),
            'date_relance_reserve_1'       => $this->parseDate((string) $this->cell($row, 'BM')),
            'date_relance_reserve_2'       => $this->parseDate((string) $this->cell($row, 'BN')),
            'situation_reserves'           => trim((string) $this->cell($row, 'BO')) ?: null,
            'date_validation_evaluateur'   => $this->parseDate((string) $this->cell($row, 'BP')),
            'date_transmission_daf'        => $this->parseDate((string) $this->cell($row, 'BQ')),
            'observations_evaluation'      => trim((string) $this->cell($row, 'BR')) ?: null,
        ];

        if (! $porteurProj->exists) {
            // Nouveau : forcer les valeurs par défaut NOT NULL
            $porteurProj->projet_id = $projet->id;
            $porteurProj->porteur_id = $porteur->id;
            foreach ($candidatsPP as $col => $val) {
                $porteurProj->$col = $val;
            }
            $porteurProj->created_by = auth()->id();
        } else {
            // Existant : merge-conservative + log conflits
            $this->mergeConservative($porteurProj, $candidatsPP, $numLigne, 'porteur_proj');
        }
        $porteurProj->updated_by = auth()->id();
        $porteurProj->save();

        // ── 5. BÉNÉFICIAIRES PRÉVUS (P–U) — multi-lieu supporté ────
        // On passe les valeurs BRUTES (strings) pour permettre le split multi-lieu
        // Ex: col N = "VAKI / DIANA / FITO" et col Q = "60 / 55 / 50"
        $regionTxt = trim((string) $this->cell($row, 'N'));
        $this->importerBenef($porteurProj, 'prevu', [
            'total'  => (string) $this->cell($row, 'P'),
            'h'      => (string) $this->cell($row, 'Q'),
            'f'      => (string) $this->cell($row, 'R'),
            'jeunes' => (string) $this->cell($row, 'S'),
            'fpe'    => (string) $this->cell($row, 'T'),
            'cadres' => (string) $this->cell($row, 'U'),
        ], $regionTxt);

        // ── 6. FORMATION PRÉVUE (V–Z) ────────────────────────────
        $this->importerFormation($porteurProj, 'prevu',
            prestataire:  trim((string) $this->cell($row, 'V')),
            modules:      trim((string) $this->cell($row, 'W')),
            formateur:    trim((string) $this->cell($row, 'Y')),
            volTotal:     $this->parseInt($this->cell($row, 'Z')),
            volParModule: $this->parseInt($this->cell($row, 'X'))
        );

        // ── 7. PAIEMENTS J1/J2/J3 (AP–AU) ────────────────────────
        $this->importerPaiements($porteurProj, [
            'J1' => [$this->cell($row, 'AP'), $this->cell($row, 'AQ')],
            'J2' => [$this->cell($row, 'AR'), $this->cell($row, 'AS')],
            'J3' => [$this->cell($row, 'AT'), $this->cell($row, 'AU')],
        ]);

        // ── 8. RÉALISATION — Bénéficiaires réels (BS–BX) — multi-lieu ────
        $this->importerBenef($porteurProj, 'realise', [
            'total'  => (string) $this->cell($row, 'BS'),
            'h'      => (string) $this->cell($row, 'BT'),
            'f'      => (string) $this->cell($row, 'BU'),
            'jeunes' => (string) $this->cell($row, 'BV'),
            'fpe'    => (string) $this->cell($row, 'BW'),
            'cadres' => (string) $this->cell($row, 'BX'),
        ], $regionTxt);

        // ── 8. RÉALISATION — Formation réelle (BY–CC) ────────────
        $this->importerFormation($porteurProj, 'reel',
            prestataire:  trim((string) $this->cell($row, 'BY')),
            modules:      trim((string) $this->cell($row, 'BZ')),
            formateur:    trim((string) $this->cell($row, 'CB')),
            volTotal:     $this->parseInt($this->cell($row, 'CC')),
            volParModule: $this->parseInt($this->cell($row, 'CA'))
        );

        // NB : les partenaires sont importés depuis la feuille séparée "Partenaires"
        // via importerFeuillePartenaires() — plus dans les colonnes I/J/K.

        $this->imported++;
    }

    /**
     * Résout un évaluateur par son nom (matching sur User).
     * Retourne null si vide ou non trouvé.
     */
    protected function resoudreEvaluateur(string $nom): ?int
    {
        $nom = trim($nom);
        if (blank($nom)) return null;

        $nomNorm = \App\Models\ImportMapping::normaliser($nom);
        if (! $nomNorm) return null;

        // Chercher par match exact puis par match normalisé sur name/email
        return \App\Models\User::query()
            ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(name,'-',''),' ',''),'.','')) = ?", [$nomNorm])
            ->value('id');
    }

    // =========================================================
    // Import de la feuille "Partenaires" du nouveau template
    // =========================================================

    /**
     * Lit la feuille "Partenaires" du fichier Excel (si elle existe) et crée
     * les partenaires. Matching souple (stratégie A) :
     *   - Cherche le projet par référence projet normalisée
     *   - Sinon par référence convention normalisée (via porteur_proj ou convention)
     *   - Sinon → rejet documenté dans partenairesRejets
     *
     * À appeler APRÈS Excel::import($this, $chemin) pour que les projets existent.
     */
    public function importerFeuillePartenaires(string $chemin): void
    {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($chemin);
        } catch (\Throwable $e) {
            Log::warning("Impossible d'ouvrir la feuille Partenaires : " . $e->getMessage());
            return;
        }

        // Chercher la feuille "Partenaires" (insensible à la casse et aux espaces)
        $feuille = null;
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $nom = mb_strtolower(trim($sheet->getTitle()));
            if ($nom === 'partenaires' || $nom === 'partenaire') {
                $feuille = $sheet;
                break;
            }
        }

        if ($feuille === null) {
            return; // feuille absente = ancien template, rien à faire
        }

        // 2e paramètre à true : les classeurs de la DEES calculent certains
        // totaux par formule. Sans évaluation, on lirait le TEXTE de la
        // formule — « =SUM(Tableau23[[#This Row],[Homme]]...) » — dont on
        // extrayait par erreur les chiffres du nom de table.
        $lignes = $feuille->toArray(null, true, false, false);

        // Colonnes du nouveau template (v3) :
        //   A=Ref projet, B=Ref convention, C=Intitulé (info), D=Porteur (info),
        //   E=Date convention (info), F=Nom partenaire, G=CNaPS, H=Nb salariés
        //
        // Rétro-compatibilité template v2 (6 colonnes) :
        //   A=Ref projet, B=Ref convention, C=Nom, D=CNaPS, E=Nb salariés, F=Rôle
        $estTemplateV3 = $this->detecterTemplateV3Partenaires($feuille);

        // Forward-fill : mémorise les dernières références rencontrées
        $lastRefProjet = '';
        $lastRefConv   = '';

        // Données à partir de la ligne 4 (index 3) — lignes 1-3 = en-têtes
        for ($i = 3; $i < count($lignes); $i++) {
            $numLigne = $i + 1; // 1-based
            $row = $lignes[$i];

            if ($estTemplateV3) {
                $refProjet = trim((string) ($row[0] ?? ''));
                $refConv   = trim((string) ($row[1] ?? ''));
                // C, D, E = infos indicatives (non utilisées à l'import)
                $nom       = trim((string) ($row[5] ?? ''));
                $cnaps     = trim((string) ($row[6] ?? ''));
                $nbSal     = $this->parseInt($row[7] ?? null);
            } else {
                $refProjet = trim((string) ($row[0] ?? ''));
                $refConv   = trim((string) ($row[1] ?? ''));
                $nom       = trim((string) ($row[2] ?? ''));
                $cnaps     = trim((string) ($row[3] ?? ''));
                $nbSal     = $this->parseInt($row[4] ?? null);
            }

            // Ligne exemple pré-remplie du template → à ignorer
            if (str_contains(mb_strtolower($nom), 'exemple')) continue;

            // Bannière d'instruction du template → à ignorer
            if (str_contains(mb_strtolower($refProjet), 'supprimez')) continue;

            // Ligne vide → fin ou trou, on passe
            if (blank($nom) && blank($refProjet) && blank($refConv)) continue;

            // ─── Forward-fill : si réfs vides, on hérite de la ligne précédente ───
            if (blank($refProjet) && blank($refConv)) {
                $refProjet = $lastRefProjet;
                $refConv   = $lastRefConv;
            } else {
                // On met à jour la mémoire uniquement quand des valeurs sont fournies
                if (filled($refProjet)) $lastRefProjet = $refProjet;
                if (filled($refConv))   $lastRefConv   = $refConv;
            }

            if (blank($nom)) {
                $this->partenairesRejets[] = "Ligne Partenaires {$numLigne} : nom manquant";
                $this->partenairesNonAssocies++;
                continue;
            }

            if (blank($refProjet) && blank($refConv)) {
                $this->partenairesRejets[] = "Ligne Partenaires {$numLigne} : « {$nom} » — aucune référence projet/convention (ni sur cette ligne, ni au-dessus)";
                $this->partenairesNonAssocies++;
                continue;
            }

            // ── Recherche du projet (stratégie A : refProjet OU refConv) ──
            $porteurProj = $this->trouverPorteurProj($refProjet, $refConv);

            if ($porteurProj === null) {
                $ref = filled($refProjet) ? $refProjet : $refConv;
                $this->partenairesRejets[] = "Ligne Partenaires {$numLigne} : projet introuvable (« {$ref} »)";
                $this->partenairesNonAssocies++;
                continue;
            }

            $nomNorm = $this->normaliserNom($nom);
            Partenaire::updateOrCreate(
                ['porteur_proj_id' => $porteurProj->id, 'nom' => $nomNorm],
                [
                    'cnaps'       => $cnaps !== '' ? $cnaps : null,
                    'nb_salaries' => $nbSal,
                ]
            );
            $this->partenairesImportes++;
        }
    }

    /**
     * Détecte la version du template (v2 = 6 col, v3 = 8 col avec infos).
     * Regarde l'en-tête ligne 2 col C : "Intitulé de projet" → v3, sinon v2.
     */
    protected function detecterTemplateV3Partenaires(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $feuille): bool
    {
        $entete = mb_strtolower((string) $feuille->getCell('C2')->getValue());
        return str_contains($entete, 'intitul');
    }

    /**
     * Trouve un PorteurProj à partir d'une référence projet ou convention (souple).
     * Retourne le premier trouvé (cas normal : 1 projet = 1 porteur).
     */
    protected function trouverPorteurProj(string $refProjet, string $refConv): ?PorteurProj
    {
        $refProjetNorm = Projet::normaliserReference($refProjet);
        $refConvNorm   = PorteurProj::normaliserReference($refConv);

        // 1) Match sur reference_normalisee du projet
        if ($refProjetNorm !== null) {
            $projet = Projet::where('reference_normalisee', $refProjetNorm)->first();
            if ($projet) {
                $pp = PorteurProj::where('projet_id', $projet->id)->first();
                if ($pp) return $pp;
            }
        }

        // 2) Match sur reference_convention_normalisee du porteur_proj
        if ($refConvNorm !== null) {
            $pp = PorteurProj::where('reference_convention_normalisee', $refConvNorm)->first();
            if ($pp) return $pp;
        }

        // 3) Match sur convention.reference_normalisee → projets → porteur_proj
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

    // =========================================================
    // Helpers entités liées
    // =========================================================

    protected function importerPartenaires(PorteurProj $pp, array $row): void
    {
        $texte      = trim((string) $this->cell($row, 'I'));
        $cnaps      = trim((string) $this->cell($row, 'J'));
        $nbSalaries = $this->parseInt($this->cell($row, 'K'));

        if (blank($texte)) return;

        $noms      = array_values(array_filter(array_map('trim', preg_split('/[;,\/\n]+/', $texte))));
        $cnapsList = array_values(array_filter(array_map('trim', preg_split('/[;,\/\n]+/', $cnaps))));

        foreach ($noms as $i => $nom) {
            $nomNorm = $this->normaliserNom($nom);
            if (blank($nomNorm)) continue;

            Partenaire::updateOrCreate(
                ['porteur_proj_id' => $pp->id, 'nom' => $nomNorm],
                [
                    'cnaps'       => $cnapsList[$i] ?? null,
                    'nb_salaries' => $nbSalaries,
                ]
            );
        }
    }

    /** Séparateurs acceptés pour multi-lieu (col N et cols bénéf) */
    private const SEPARATEURS_MULTI = ['/', ',', ';', '|', "\n"];

    /**
     * Import de bénéficiaires — supporte le multi-lieu.
     *
     * $rawValeurs contient les valeurs BRUTES (strings) de l'Excel qui peuvent
     * contenir des séparateurs. Ex: 'total' => "100 / 100 / 100"
     * $regionText peut aussi être multi : "VAKINANKARATRA / AMBANJA / MANAKARA"
     *
     * Créera N records benef (1 par lieu détecté). Si mono-lieu → 1 record.
     */
    protected function importerBenef(PorteurProj $pp, string $type, array $rawValeurs, string $regionText = ''): void
    {
        // Normaliser type (Excel dit 'reel', DB dit 'realise')
        if ($type === 'reel') $type = 'realise';

        // ─── 1) Split de la colonne région pour détecter les lieux ───
        [$lieux, $separateur] = $this->splitMultiValue($regionText);
        $nbLieux = max(1, count($lieux)); // au moins 1 même si vide

        // ─── 2) Parser chaque colonne bénéf ───
        // Retourne un tableau [total => [100,100,100], h => [60,55,50], ...]
        $valeursParChamp = [];
        $reparteesAuto = false;   // flag pour savoir si on a fait la répartition auto
        $formulesRencontrees = [];
        foreach (['total', 'h', 'f', 'jeunes', 'fpe', 'cadres'] as $champ) {
            $raw = $rawValeurs[$champ] ?? 0;

            // Une formule non évaluée n'est ni un nombre ni une liste de lieux :
            // la découper sur ses virgules produisait des fragments absurdes
            // (« Tableau23 » → 23) qui atterrissaient en base comme effectifs.
            if ($this->estFormule($raw)) {
                $valeursParChamp[$champ] = array_fill(0, $nbLieux, 0);
                $formulesRencontrees[] = $champ;
                continue;
            }

            [$parts, $sep] = $this->splitMultiValue((string) $raw);
            $nbParts = count($parts);

            // Cellule vide (nbParts=0) = équivalent à valeur unique = 0
            if ($nbParts === 0) {
                $valeursParChamp[$champ] = array_fill(0, $nbLieux, 0);
                continue;
            }

            if ($nbParts === $nbLieux) {
                // Cas idéal : valeurs pré-ventilées dans l'Excel
                $valeursParChamp[$champ] = array_map(fn($v) => $this->parseInt($v), $parts);
            } elseif ($nbParts === 1) {
                // Valeur unique → à répartir équitablement
                $valUnique = $this->parseInt($parts[0] ?? 0);
                if ($nbLieux > 1 && $valUnique > 0) {
                    $reparteesAuto = true;
                    $parEquip = intdiv($valUnique, $nbLieux);
                    $reste = $valUnique - ($parEquip * $nbLieux);
                    $valeursParChamp[$champ] = array_fill(0, $nbLieux, $parEquip);
                    $valeursParChamp[$champ][0] += $reste; // reste au 1er lieu
                } else {
                    $valeursParChamp[$champ] = array_fill(0, $nbLieux, $valUnique);
                }
            } else {
                // Divergence RÉELLE : nbParts>1 mais != nbLieux → conflit
                $this->logConflict('benef', $pp->id, $champ,
                    valeurDb: "$nbLieux lieux détectés",
                    valeurExcel: "$nbParts valeurs pour \"$raw\"",
                    numLigne: 0
                );
                $vals = array_map(fn($v) => $this->parseInt($v), $parts);
                $vals = array_pad($vals, $nbLieux, 0);
                $valeursParChamp[$champ] = array_slice($vals, 0, $nbLieux);
            }
        }

        // ─── 2bis) Repli du total quand il venait d'une formule ───
        // Dans les classeurs de la DEES, « Nb bénéf total » est un
        // =SUM(Homme, Femme). La formule n'étant pas évaluée à la lecture, on la
        // recalcule nous-mêmes plutôt que de laisser un total à zéro face à des
        // effectifs renseignés — ce qui aurait donné des répartitions par sexe
        // supérieures au total dans le tableau de bord.
        if (in_array('total', $formulesRencontrees, true)) {
            foreach ($valeursParChamp['total'] as $i => $valeur) {
                if ($valeur === 0) {
                    $valeursParChamp['total'][$i] =
                        ($valeursParChamp['h'][$i] ?? 0) + ($valeursParChamp['f'][$i] ?? 0);
                }
            }
        }

        if ($formulesRencontrees !== []) {
            $this->errors[] = 'Projet ' . ($pp->reference_convention ?: $pp->id)
                . ' : colonne(s) ' . implode(', ', array_unique($formulesRencontrees))
                . ' contenant une formule Excel non calculée'
                . (in_array('total', $formulesRencontrees, true) ? ' — total recalculé (H + F).' : '.');
        }

        // ─── 3) Skip si toutes valeurs à 0 ───
        $totalGlobal = array_sum($valeursParChamp['total']);
        $sommeAll = 0;
        foreach ($valeursParChamp as $vals) $sommeAll += array_sum($vals);
        if ($totalGlobal === 0 && $sommeAll === 0) {
            return;
        }

        // ─── 4) Détection de la région/commune par lieu ───
        $resolutions = [];
        foreach ($lieux ?: [$regionText] as $lieu) {
            $resolutions[] = $this->resoudreLieu($lieu);
        }
        if (empty($resolutions)) $resolutions[] = ['region_id' => null, 'commune_id' => null];

        // ─── 5) Effacer les anciens benef de ce (porteur_proj, type) puis recréer ───
        // C'est plus safe que updateOrCreate car le nombre de lieux peut changer
        Benef::where('porteur_proj_id', $pp->id)->where('type', $type)->delete();

        $source = $reparteesAuto ? Benef::SOURCE_EXCEL_REPARTI_AUTO : Benef::SOURCE_EXCEL_PRECIS;
        for ($i = 0; $i < $nbLieux; $i++) {
            $reso = $resolutions[$i] ?? ['region_id' => null, 'commune_id' => null];
            Benef::create([
                'porteur_proj_id' => $pp->id,
                'type'            => $type,
                'region_id'       => $reso['region_id'],
                'commune_id'      => $reso['commune_id'],
                'total'           => $valeursParChamp['total'][$i] ?? 0,
                'h'               => $valeursParChamp['h'][$i] ?? 0,
                'f'               => $valeursParChamp['f'][$i] ?? 0,
                'jeunes'          => $valeursParChamp['jeunes'][$i] ?? 0,
                'fpe'             => $valeursParChamp['fpe'][$i] ?? 0,
                'cadres'          => $valeursParChamp['cadres'][$i] ?? 0,
                'source'          => $source,
            ]);
        }
    }

    /**
     * Split une valeur potentiellement multi-lieu.
     * Détecte automatiquement le séparateur utilisé parmi /, ,, ;, |, \n.
     *
     * @return array{0: array<int, string>, 1: string|null}  [parts, separateur_detecte]
     */
    protected function splitMultiValue(string $texte): array
    {
        $texte = trim($texte);
        if ($texte === '') return [[], null];

        // Chercher le 1er séparateur qui apparaît
        foreach (self::SEPARATEURS_MULTI as $sep) {
            if (str_contains($texte, $sep)) {
                $parts = array_map('trim', explode($sep, $texte));
                $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
                if (count($parts) >= 2) return [$parts, $sep];
            }
        }

        return [[$texte], null];
    }

    /**
     * Résout un nom de lieu (peut être région, commune, district, fokontany)
     * en (region_id, commune_id).
     *
     * @return array{region_id: int|null, commune_id: int|null}
     */
    protected function resoudreLieu(string $texte): array
    {
        $texte = trim($texte);
        if ($texte === '') return ['region_id' => null, 'commune_id' => null];

        $norm = \App\Models\ImportMapping::normaliser($texte);
        if (! $norm) return ['region_id' => null, 'commune_id' => null];

        // 1) Match direct sur région
        $reg = \App\Models\Region::whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(UPPER(libelle),'-',''),'_',''),' ',''),'''','') = ?", [$norm])->first();
        if ($reg) return ['region_id' => $reg->id, 'commune_id' => null];

        // 2) Match sur commune → région parente
        $com = \App\Models\Commune::where('libelle_normalise', $norm)->first();
        if ($com) return ['region_id' => $com->region_id, 'commune_id' => $com->id];

        // 3) Match sur district → région parente
        $dis = \App\Models\District::where('libelle_normalise', $norm)->first();
        if ($dis) return ['region_id' => $dis->region_id, 'commune_id' => null];

        // 4) Fallback : sur fokontany → région + commune parentes
        $fok = \App\Models\Fokontany::where('libelle_normalise', $norm)->first();
        if ($fok) return ['region_id' => $fok->region_id, 'commune_id' => $fok->commune_id];

        return ['region_id' => null, 'commune_id' => null];
    }

    protected function importerFormation(
        PorteurProj $pp,
        string $type,
        string $prestataire,
        string $modules,
        string $formateur,
        int $volTotal,
        int $volParModule
    ): void {
        // Normaliser type : Excel dit 'reel', DB enum accepte 'realise'
        if ($type === 'reel') $type = 'realise';

        if (blank($prestataire) && blank($modules) && $volTotal === 0) return;

        $formation = Formation::updateOrCreate(
            ['porteur_proj_id' => $pp->id, 'type' => $type],
            ['volume_horaire_total' => $volTotal, 'created_by' => auth()->id()]
        );

        if (filled($prestataire)) {
            $presta = Prestataire::firstOrCreate(['nom' => $prestataire]);
            PrestaForm::firstOrCreate([
                'prestataire_id' => $presta->id,
                'formation_id'   => $formation->id,
            ]);
        }

        $formateurModel = filled($formateur) ? Formateur::firstOrCreate(['nom' => $formateur]) : null;

        if (filled($modules)) {
            $listeModules = array_filter(array_map('trim', preg_split('/[;\n]+/', $modules)));
            foreach ($listeModules as $modNom) {
                $module = Module::firstOrCreate(['intitule' => Str::limit($modNom, 300)]);
                FormMod::updateOrCreate(
                    ['formation_id' => $formation->id, 'module_id' => $module->id],
                    [
                        'formateur_id'   => $formateurModel?->id,
                        'volume_horaire' => $volParModule,
                    ]
                );
            }
        }
    }

    protected function importerPaiements(PorteurProj $pp, array $lignes): void
    {
        foreach ($lignes as $ligne => [$dateBrute, $montantBrut]) {
            $date    = $this->parseDate((string) $dateBrute);
            $montant = $this->parseMontant($montantBrut);
            if (blank($date) || $montant === 0) continue;

            Paiement::updateOrCreate(
                ['porteur_proj_id' => $pp->id, 'ligne' => $ligne, 'date_paiement' => $date],
                ['montant' => $montant, 'created_by' => auth()->id()]
            );
        }
    }

    // =========================================================
    // Résolution référentiels
    // =========================================================

    protected function resoudrePorteur(array $row, ?string $nomOverride = null, ?int $numLigne = null): Porteur
    {
        // Mapping template actuel : F=Porteur G=CNaPS H=NbSal
        // J=Contact K=Tel L=Email M=Adresse N=Région
        $nomBrut = $nomOverride ?? $this->normaliserNom((string) $this->cell($row, 'F'));

        // Norme SEER : matching agressif ("ACM-NET" == "ACM NET" == "Acm Net" → ACMNET)
        // Stockage en UPPER_SNAKE_CASE (ACM_NET)
        $nomStockage    = \App\Models\ImportMapping::formaterLibelle($nomBrut) ?: $nomBrut;
        $nomNormalisee  = \App\Models\ImportMapping::normaliser($nomBrut);

        $region  = $this->resoudreRegion((string) $this->cell($row, 'N'));
        $secteur = $this->resoudreSecteur((string) $this->cell($row, 'A'));

        $candidats = [
            'cnaps'           => trim((string) $this->cell($row, 'G')) ?: null,
            'nb_salaries'     => $this->parseInt($this->cell($row, 'H')),
            'responsable_nom' => trim((string) $this->cell($row, 'J')) ?: null,
            'telephone'       => trim((string) $this->cell($row, 'K')) ?: null,
            'email'           => trim((string) $this->cell($row, 'L')) ?: null,
            'adresse'         => trim((string) $this->cell($row, 'M')) ?: null,
            'region_id'       => $region?->id,
            'secteur_id'      => $secteur?->id,
        ];

        // ── Matching normalisé (jamais bloqué par CNaPS vide) ──
        $porteur = Porteur::withTrashed()->where('raison_sociale_normalisee', $nomNormalisee)->first();

        if ($porteur) {
            // Existant : merge-conservative + log conflits (notamment CNaPS divergent)
            $this->mergeConservative($porteur, $candidats, $numLigne ?? 0, 'porteur');
            $porteur->save();
            return $porteur;
        }

        // Nouveau porteur
        return Porteur::create([
            'raison_sociale'            => $nomStockage,       // ACM_NET
            'raison_sociale_normalisee' => $nomNormalisee,     // ACMNET
            'cnaps'                     => $candidats['cnaps'],
            'nb_salaries'               => $candidats['nb_salaries'] ?: 0,
            'responsable_nom'           => $candidats['responsable_nom'],
            'telephone'                 => $candidats['telephone'],
            'email'                     => $candidats['email'],
            'adresse'                   => $candidats['adresse'],
            'region_id'                 => $candidats['region_id'],
            'secteur_id'                => $candidats['secteur_id'],
            'created_by'                => auth()->id(),
        ]);
    }

    /**
     * Applique un mapping mémorisé (ImportMapping) avant de tenter la résolution normale.
     * Retourne l'ID cible si un mapping existe, sinon null.
     */
    protected function appliquerMapping(string $referentiel, string $valeurSaisie, string $modelClass): mixed
    {
        $norm = \App\Models\ImportMapping::normaliser($valeurSaisie);
        if ($norm === null) return null;

        $mapping = \App\Models\ImportMapping::where('referentiel', $referentiel)
            ->where('valeur_saisie_normalisee', $norm)
            ->first();

        if (! $mapping) return null;

        if ($mapping->action === 'ignore') return 'ignore';

        if ($mapping->action === 'map' && $mapping->target_id) {
            return $modelClass::find($mapping->target_id);
        }

        // action = 'create' → laisser passer au firstOrCreate normal
        return null;
    }

    protected function resoudreRegion(string $texte): ?Region
    {
        $texte = trim($texte);
        if (blank($texte)) return null;

        // ─── 0) MULTI-LIEU : ne rien créer, prendre la 1ère région parsable ───
        // Ex: "VAKI / AMBANJA / MANAKARA" → split, résoudre chaque lieu, prendre le 1er valide
        [$parts, ] = $this->splitMultiValue($texte);
        if (count($parts) > 1) {
            foreach ($parts as $part) {
                $reso = $this->resoudreLieu($part);
                if ($reso['region_id']) {
                    return Region::find($reso['region_id']);
                }
            }
            return null; // Aucun lieu parsable → NULL, projet sans région primaire
        }

        // ─── 1) Mapping mémorisé prioritaire ───
        $mappe = $this->appliquerMapping('region', $texte, Region::class);
        if ($mappe instanceof Region) return $mappe;
        if ($mappe === 'ignore') return null;

        // ─── 1.b) Résolution par géographie (commune/district/fokontany) ───
        $reso = $this->resoudreLieu($texte);
        if ($reso['region_id']) {
            $reg = Region::find($reso['region_id']);
            if ($reg) return $reg;
        }

        $corrections = ['VAKINAKARATRA' => 'VAKINANKARATRA'];
        $texte = $corrections[mb_strtoupper($texte)] ?? $texte;

        $cacheKey = mb_strtoupper($texte);
        if (isset($this->cacheRegions[$cacheKey])) {
            return $this->cacheRegions[$cacheKey];
        }

        // ─── 1.c) Fuzzy match ≥85% avec une région existante (auto-correction typos) ───
        $meilleur = null; $sim = 0;
        $norm = \App\Models\ImportMapping::normaliser($texte);
        if ($norm) {
            foreach (Region::pluck('libelle', 'id') as $id => $lib) {
                similar_text($norm, \App\Models\ImportMapping::normaliser($lib) ?? '', $p);
                if ($p > $sim) { $sim = $p; $meilleur = Region::find($id); }
            }
            if ($sim >= 85.0 && $meilleur) {
                return $this->cacheRegions[$cacheKey] = $meilleur;
            }
        }

        // Recherche partielle (texte libre)
        $existant = Region::where('libelle', 'LIKE', '%' . $texte . '%')->first();
        if ($existant) {
            return $this->cacheRegions[$cacheKey] = $existant;
        }

        // ⚠ VERROU STRICT : NE JAMAIS créer de nouvelle région à l'import.
        // Il n'existe que 23 régions officielles à Madagascar. Si aucune ne matche,
        // le projet reste sans région (region_id = null). Le wizard gère les inconnus.
        return $this->cacheRegions[$cacheKey] = null;
    }

    protected function resoudreGuichet(string $texte): ?\App\Models\Guichet
    {
        $texte = trim($texte);
        if (blank($texte)) return null;

        // ─── Mapping mémorisé prioritaire ───
        $mappe = $this->appliquerMapping('guichet', $texte, \App\Models\Guichet::class);
        if ($mappe instanceof \App\Models\Guichet) return $mappe;
        if ($mappe === 'ignore') return null;

        $code = Str::limit(mb_strtoupper(preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($texte))), 20, '');
        if (blank($code)) return null;

        // Cache mémoire pour éviter SELECT/INSERT répétés
        if (isset($this->cacheGuichets[$code])) {
            return $this->cacheGuichets[$code];
        }

        return $this->cacheGuichets[$code] = \App\Models\Guichet::firstOrCreate(
            ['code' => $code],
            ['libelle' => Str::limit(\App\Models\ImportMapping::formaterLibelle($texte) ?: $texte, 150, ''), 'is_active' => true]
        );
    }

    protected function resoudreVague(string $texte): ?\App\Models\Vague
    {
        $texte = trim($texte);
        if (blank($texte)) return null;

        // ─── Mapping mémorisé prioritaire ───
        $mappe = $this->appliquerMapping('vague', $texte, \App\Models\Vague::class);
        if ($mappe instanceof \App\Models\Vague) return $mappe;
        if ($mappe === 'ignore') return null;

        $code = Str::limit(mb_strtoupper(preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($texte))), 20, '');
        if (blank($code)) return null;

        if (isset($this->cacheVagues[$code])) {
            return $this->cacheVagues[$code];
        }

        $annee = null;
        if (preg_match('/(\d{4})/', $texte, $m)) $annee = (int) $m[1];

        return $this->cacheVagues[$code] = \App\Models\Vague::firstOrCreate(
            ['code' => $code],
            ['libelle' => Str::limit(\App\Models\ImportMapping::formaterLibelle($texte) ?: $texte, 150, ''), 'annee' => $annee, 'is_active' => true]
        );
    }

    protected function resoudreSecteur(string $texte): ?Secteur
    {
        $texte = trim($texte);
        if (blank($texte)) return null;

        // ─── Mapping mémorisé prioritaire ───
        $mappe = $this->appliquerMapping('secteur', $texte, Secteur::class);
        if ($mappe instanceof Secteur) return $mappe;
        if ($mappe === 'ignore') return null;

        $normalisee = \App\Models\ImportMapping::normaliser($texte);
        if ($normalisee === null) return null;

        // Index normalisé de la nomenclature officielle, construit une seule
        // fois. On indexe le code ET le libellé : « BTP/RS », « BTP_RS » et
        // « Bâtiment / Travaux Publics / Ressources Stratégiques » mènent au
        // même secteur une fois normalisés.
        if (! $this->cacheSecteursCharge) {
            foreach (Secteur::all() as $secteur) {
                foreach ([$secteur->code, $secteur->libelle] as $forme) {
                    $cle = \App\Models\ImportMapping::normaliser((string) $forme);
                    if ($cle !== null && ! isset($this->cacheSecteurs[$cle])) {
                        $this->cacheSecteurs[$cle] = $secteur;
                    }
                }
            }
            $this->cacheSecteursCharge = true;
        }

        if (isset($this->cacheSecteurs[$normalisee])) {
            return $this->cacheSecteurs[$normalisee];
        }

        // Nomenclature FERMÉE : un fichier ne crée JAMAIS de secteur.
        // Auparavant, un firstOrCreate inventait une entrée par graphie — d'où
        // 54 secteurs pour 11 réels, et une répartition éclatée dans le tableau
        // de bord, qui agrège par libellé.
        //
        // Le projet entre sans secteur : la DEES le complétera depuis
        // l'interface, ou enregistrera un alias via le wizard de préflight, qui
        // vaudra alors pour tous les imports suivants.
        if (! in_array($texte, $this->secteursInconnus, true)) {
            $this->secteursInconnus[] = $texte;
            $this->errors[] = "Secteur « {$texte} » hors nomenclature FMFP : projet importé sans secteur.";
        }

        return null;
    }

    protected function resoudreStatut(string $texte): ?StatutProjet
    {
        $texte = trim($texte);
        if (blank($texte)) $texte = 'incomplet';

        // ─── Mapping mémorisé prioritaire ───
        $mappe = $this->appliquerMapping('statut', $texte, StatutProjet::class);
        if ($mappe instanceof StatutProjet) return $mappe;
        if ($mappe === 'ignore') return null;

        $code = $this->codifierStatut($texte) ?: 'incomplet';

        if (isset($this->cacheStatuts[$code])) {
            return $this->cacheStatuts[$code];
        }

        return $this->cacheStatuts[$code] = StatutProjet::firstOrCreate(
            ['code' => $code],
            [
                'libelle' => Str::limit(mb_convert_case(mb_strtolower($texte), MB_CASE_TITLE, 'UTF-8'), 100, ''),
                'ordre'   => 99,
            ]
        );
    }

    /**
     * Génère un code court (max 30 char) pour un statut, en supprimant
     * les accents et caractères spéciaux. Ex: "FINI ET CLOTURE" → "fini_cloture"
     */
    protected function codifierStatut(string $texte): string
    {
        $t = mb_strtolower(trim($texte));
        // Map des variantes connues vers codes standardisés DEES
        $map = [
            // ── STATUTS PRINCIPAUX (6 statuts métier) ──────────────
            'stand by'                        => 'stand_by',
            'standby'                         => 'stand_by',
            'attente_peces_regul'             => 'attente_pieces_regul',
            'attente pieces regul'            => 'attente_pieces_regul',
            'attente pièces régul'            => 'attente_pieces_regul',
            'attente pièces régularisation'   => 'attente_pieces_regul',
            'validation financiere'           => 'validation_financiere',
            'validation financière'           => 'validation_financiere',
            'formation_encours'               => 'formation_encours',
            'formation en cours'              => 'formation_encours',
            'cloture'                         => 'cloture',
            'clôturé'                         => 'cloture',
            'cloturé'                         => 'cloture',
            'annule'                          => 'annule',
            'annulé'                          => 'annule',

            // ── STATUTS HISTORIQUES ────────────────────────────────
            'valide'    => 'valide',    'validé'      => 'valide',
            'refuse'    => 'refuse',    'refusé'      => 'refuse',
            'resilie'   => 'resilie',   'résilié'     => 'resilie',
            'inelig'    => 'inelig',    'inéligible'  => 'inelig',
            'ineligible' => 'inelig',
            'incomplet' => 'incomplet',
            'en cours'  => 'en_cours',
            'fini et cloture'                => 'fini_cloture',
            'fini et attente rapport'        => 'fini_attente_rapport',
            'non realise'                    => 'non_realise',
            'non réalisé'                    => 'non_realise',
            'en traitement au fmfp'          => 'en_traitement_fmfp',
            'attente calendrier + rib (dees)'=> 'attente_calendrier_rib',
            'phase de contractualisation'    => 'phase_contractualisation',
            'projet ap/ri a cloturer'        => 'a_cloturer',
        ];

        if (isset($map[$t])) return $map[$t];

        // Sinon : transformer "FINI ET ATTENTE RAPPORT" en "fini_et_attente_rapport"
        $code = preg_replace('/[^a-z0-9]+/', '_', $this->retirerAccents($t));
        $code = trim($code, '_');
        return Str::limit($code, 30, '');
    }

    // ────────────────────────────────────────────────────────────
    // Norme SEER — merge-conservative + journal des conflits
    // ────────────────────────────────────────────────────────────

    /**
     * Applique la règle merge-conservative à un modèle existant :
     * - champ DB vide + Excel rempli   → REMPLIR
     * - champ DB rempli + Excel vide   → GARDER DB (jamais écraser par vide)
     * - champ DB = Excel                → aucune action
     * - champ DB ≠ Excel (les 2 remplis) → GARDER DB + LOG conflit
     *
     * @param \Illuminate\Database\Eloquent\Model $modele
     * @param array<string, mixed> $candidats  Valeurs Excel indexées par nom de colonne
     * @param int $numLigne  Numéro de ligne Excel pour traçabilité
     * @param string|null $entiteType  'projet', 'porteur', 'porteur_proj' (auto-détecté si null)
     */
    protected function mergeConservative(
        \Illuminate\Database\Eloquent\Model $modele,
        array $candidats,
        int $numLigne,
        ?string $entiteType = null,
    ): void {
        $entiteType ??= strtolower(class_basename($modele));

        foreach ($candidats as $champ => $valeurExcel) {
            $valeurDb = $modele->$champ ?? null;
            $dbVide = ($valeurDb === null || $valeurDb === '' || $valeurDb === 0);
            $excelVide = ($valeurExcel === null || $valeurExcel === '' || $valeurExcel === 0);

            if ($dbVide && ! $excelVide) {
                // Enrichissement : DB était vide, on remplit
                $modele->$champ = $valeurExcel;
                continue;
            }
            if (! $dbVide && $excelVide) {
                // Ne rien faire : DB a une valeur, Excel est vide → GARDER DB
                continue;
            }
            if ($dbVide && $excelVide) {
                // Rien à faire
                continue;
            }

            // Les deux sont remplis : sont-ils identiques ?
            if ((string) $valeurDb === (string) $valeurExcel) {
                continue; // Même valeur, pas de conflit
            }

            // ⚠ Divergence détectée : GARDER DB + LOG conflit
            $this->logConflict($entiteType, $modele->id ?? null, $champ, $valeurDb, $valeurExcel, $numLigne);
        }
    }

    /**
     * Journalise un conflit dans la table import_conflicts.
     * UPDATEORCREATE : évite de dupliquer le même conflit (entite+champ) à chaque
     * ligne Excel qui passe. 1 seul enregistrement par (entite_type, entite_id, champ).
     */
    protected function logConflict(
        string $entiteType,
        ?int $entiteId,
        string $champ,
        mixed $valeurDb,
        mixed $valeurExcel,
        int $numLigne,
    ): void {
        \App\Models\ImportConflict::updateOrCreate(
            [
                'entite_type' => $entiteType,
                'entite_id'   => $entiteId,
                'champ'       => $champ,
                'statut'      => \App\Models\ImportConflict::STATUT_EN_ATTENTE,
            ],
            [
                'valeur_db'    => is_scalar($valeurDb) ? (string) $valeurDb : json_encode($valeurDb),
                'valeur_excel' => is_scalar($valeurExcel) ? (string) $valeurExcel : json_encode($valeurExcel),
                'ligne_excel'  => $numLigne,
                'fichier'      => $this->fichierEnCours ?? null,
            ]
        );
    }

    /** Nom du fichier en cours d'import — set par le wizard */
    public ?string $fichierEnCours = null;

    protected function retirerAccents(string $s): string
    {
        return strtr($s, [
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'à'=>'a','â'=>'a','ä'=>'a',
            'î'=>'i','ï'=>'i','í'=>'i',
            'ô'=>'o','ö'=>'o','ò'=>'o',
            'ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
        ]);
    }

    // =========================================================
    // Normalisation
    // =========================================================

    protected function normaliserNom(string $nom): string
    {
        $nom = mb_strtoupper(trim($nom));
        foreach (['SARL', 'SA', 'SAS', 'EURL', 'SNC', 'SCI', 'SCOP', 'GIE', 'ASSOCIATION', 'ONG'] as $s) {
            $nom = preg_replace('/[\s,]+' . preg_quote($s, '/') . '[\s,]*$/i', '', $nom);
        }
        return trim($nom);
    }

    protected function normaliserAlerte(string $texte): string
    {
        $t = mb_strtolower(trim($texte));
        if (str_contains($t, 'rouge'))  return 'rouge';
        if (str_contains($t, 'orange')) return 'orange';
        return 'verte';
    }

    protected function normaliserStatutValidation(string $texte): string
    {
        // Délègue à codifierStatut pour gérer tous les cas (12 statuts du fichier DEES)
        $code = $this->codifierStatut($texte);
        return $code ?: 'incomplet';
    }

    protected function normaliserSituationAlloc(string $texte): string
    {
        $t = mb_strtolower(trim($texte));
        if (str_contains($t, 'clôtur') || str_contains($t, 'clotur')) return 'solde';
        if (str_contains($t, 'annul'))  return 'annule';
        if (str_contains($t, 'cours'))  return 'partiel';
        if (str_contains($t, 'stand'))  return 'partiel';
        return 'non_verse';
    }

    protected function parseDate(string $valeur): ?string
    {
        $valeur = trim($valeur);
        if (blank($valeur)) return null;

        try {
            // Excel stocke normalement les dates en numéro de série : c'est le
            // cas le plus fiable, on le traite en premier.
            if (is_numeric($valeur)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(
                    (float) $valeur
                )->format('Y-m-d');
            }

            // Cellule au format TEXTE : les fichiers de la DEES sont en français,
            // donc jj/mm/aaaa. Sans ce traitement, Carbon::parse() applique la
            // convention américaine m/d/Y et lit « 01/02/2026 » comme le
            // 2 janvier au lieu du 1er février — silencieusement. Pire, dès que
            // le jour dépasse 12 (« 15/06/2026 »), le mois est invalide et la
            // date devient null, ce qui exclut définitivement le projet du
            // job d'alertes, qui filtre sur whereNotNull('date_fin').
            if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $valeur, $m)) {
                $date = \Carbon\Carbon::createFromFormat('!d/m/Y', $valeur);

                // createFromFormat déborde silencieusement (31/02 -> 3 mars) :
                // on vérifie que le jour et le mois lus sont bien ceux saisis.
                if ($date && (int) $m[1] === $date->day && (int) $m[2] === $date->month) {
                    return $date->toDateString();
                }

                return null; // date française syntaxiquement correcte mais inexistante
            }

            // strtotime() renvoie false en silence sur une valeur non datée, là
            // où Carbon::parse() émet d'abord un warning PHP avant de lever son
            // exception — ce qui polluerait les journaux de production à chaque
            // cellule contenant « n/a », un tiret ou du texte libre.
            if (strtotime($valeur) === false) {
                return null;
            }

            return Carbon::parse($valeur)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Une cellule contenant une FORMULE non évaluée n'est pas un nombre.
     *
     * Les classeurs de la DEES calculent certains totaux par formule
     * (« =SUM(Tableau23[[#This Row],[Homme]],...) »). Le lecteur renvoie alors
     * le texte de la formule, et non son résultat. Sans cette garde, parseInt
     * en extrayait les chiffres du nom de table : « Tableau23 » devenait 23,
     * et ce 23 se retrouvait comme nombre de bénéficiaires de chaque projet.
     */
    protected function estFormule(mixed $v): bool
    {
        return is_string($v) && str_starts_with(ltrim($v), '=');
    }

    protected function parseInt(mixed $v): int
    {
        if ($this->estFormule($v)) {
            return 0;
        }

        return (int) preg_replace('/[^0-9]/', '', (string) $v);
    }

    protected function parseMontant(mixed $v): int
    {
        if ($this->estFormule($v)) {
            return 0;
        }

        // On ne garde que les chiffres et les deux séparateurs possibles.
        // Tout le reste disparaît : espaces, espaces insécables d'Excel,
        // symboles monétaires, texte libre.
        $s = preg_replace('/[^0-9.,]/u', '', (string) $v);

        if ($s === '') {
            return 0;
        }

        // C'est le DERNIER séparateur qui détermine la lecture du nombre.
        $posPoint   = strrpos($s, '.');
        $posVirgule = strrpos($s, ',');

        if ($posPoint === false && $posVirgule === false) {
            return (int) $s;
        }

        $pos = max((int) $posPoint, (int) $posVirgule);
        $chiffresApres = strlen($s) - $pos - 1;

        // Exactement 3 chiffres après le dernier séparateur : c'est un
        // séparateur de MILLIERS. « 1.000.000 », « 1 000 000 » et « 1000000 »
        // désignent le même million. Sans ce cas, « 250.000.000 » était lu
        // comme 250 — une division par un million, sans aucune alerte.
        if ($chiffresApres === 3) {
            return (int) str_replace(['.', ','], '', $s);
        }

        // Sinon, le dernier séparateur est DÉCIMAL. Les montants sont des
        // Ariary entiers (bigint non signé), donc on arrondit.
        $partieEntiere = str_replace(['.', ','], '', substr($s, 0, $pos));
        $partieDecimale = substr($s, $pos + 1);

        return (int) round((float) ($partieEntiere . '.' . $partieDecimale));
    }

    protected function genererReference(): string
    {
        return sprintf('PRJ-%d-%04d', now()->year, Projet::whereYear('created_at', now()->year)->count() + 1);
    }

    // =========================================================
    // Mapping spécifique format DEES_BDD_VF.xlsx (74 colonnes)
    // ─────────────────────────────────────────────────────────
    // A=Secteur, B=Vague, C=Guichet, D=Réf. projet, E=Réf. conv.,
    // F=Porteur, G=CNaPS porteur, H=Nb salariés porteur
    // I-AO=17 partenaires (Nom + Matricule alternés)
    // AP=Nb partenaires, AQ=Intitulé, AR=Contact,
    // AS=Téléphone, AT=Email, AU=Adresse, AV=Région
    // AW=STATUT validation (NOUVEAU)
    // AX-BC=Bénéficiaires prévus (Total/H/F/Jeunes/FPE/Femmes cadres)
    // BD-BH=Formation prévue (Prestataire/Modules/Vol h/module/Formateur/Vol total)
    // BI=Montant total, BJ=Financement demandé, BK=Motifs
    // BL=Date notification, BM=Date début, BN=Date fin, BO=Situation
    // BP-BU=Bénéficiaires réalisés, BV=Volume horaire réalisé
    // =========================================================
    protected function traiterLigneFormatDeesBdd(array $row, int $numLigne): void
    {
        $nomPorteur = $this->normaliserNom((string) $this->cell($row, 'F'));
        $intitule   = trim((string) $this->cell($row, 'AQ'));

        // Le porteur est obligatoire (raison_sociale NOT NULL en DB)
        if (blank($nomPorteur)) {
            $this->skipped++;
            return;
        }

        // ── 1. PORTEUR ──────────────────────────────────────────
        $porteur = $this->resoudrePorteurDeesBdd($row);

        // ── 2. PROJET ───────────────────────────────────────────
        $refProjet = Str::limit(trim((string) $this->cell($row, 'D')), 100, '');
        $intituleLim = Str::limit($intitule, 500, '');

        $projet = blank($refProjet)
            ? Projet::firstOrNew(['intitule' => $intituleLim])
            : Projet::firstOrNew(['reference' => $refProjet]);

        if (! $projet->exists || blank($projet->reference)) {
            $projet->reference = $refProjet ?: $this->genererReference();
        }
        $projet->intitule         = $intituleLim;
        // Statut projet : col AW (STATUT) — fallback sur BO (Situation) si vide
        $statutTxt = trim((string) $this->cell($row, 'AW'));
        if (blank($statutTxt)) $statutTxt = trim((string) $this->cell($row, 'BO'));
        $projet->statut_projet_id = $this->resoudreStatut($statutTxt)?->id
            ?? StatutProjet::where('code', 'incomplet')->value('id');
        $projet->guichet_id       = $this->resoudreGuichet((string) $this->cell($row, 'C'))?->id;
        $projet->vague_id         = $this->resoudreVague((string) $this->cell($row, 'B'))?->id;
        $projet->secteur_id       = $this->resoudreSecteur((string) $this->cell($row, 'A'))?->id;
        $projet->region_id        = $this->resoudreRegion((string) $this->cell($row, 'AV'))?->id;
        $projet->date_debut       = $this->parseDate((string) $this->cell($row, 'BM'));
        $projet->date_fin         = $this->parseDate((string) $this->cell($row, 'BN'));
        $projet->created_by       = auth()->id();
        $projet->save();

        // ── 3. CONVENTION ────────────────────────────────────────
        $refConv = trim((string) $this->cell($row, 'E'));
        if (filled($refConv) && ! $projet->convention_id) {
            $conv = Convention::firstOrCreate(
                ['reference' => $refConv],
                ['created_by' => auth()->id()]
            );
            $projet->update(['convention_id' => $conv->id]);
        }

        // ── 4. PORTEUR_PROJ ──────────────────────────────────────
        $porteurProj = PorteurProj::firstOrNew([
            'projet_id'  => $projet->id,
            'porteur_id' => $porteur->id,
        ]);

        $porteurProj->fill([
            'reference_convention' => $refConv ?: null,

            // Financement (BI-BJ)
            'montant_total'        => $this->parseMontant($this->cell($row, 'BI')),
            'financement_demande'  => $this->parseMontant($this->cell($row, 'BJ')),
            'dt_mobilise'          => 0,
            'fonds_additionnel'    => 0,
            'fonds_mutualise'      => 0,
            'financement_autre'    => 0,

            // Validation : col AW (STATUT) en priorité, BO en fallback
            'appreciation_evaluateur' => '',
            'statut_validation'       => $this->normaliserStatutValidation(
                trim((string) $this->cell($row, 'AW')) ?: (string) $this->cell($row, 'BO')
            ),
            'motifs'                  => trim((string) $this->cell($row, 'BK')),

            // Dates contractualisation (BL-BN)
            'date_notification'    => $this->parseDate((string) $this->cell($row, 'BL')),
            'date_debut'           => $this->parseDate((string) $this->cell($row, 'BM')),
            'date_fin'             => $this->parseDate((string) $this->cell($row, 'BN')),

            // Situation allocation : col BO (Situation)
            'situation_alloc'      => $this->normaliserSituationAlloc((string) $this->cell($row, 'BO')),

            // Alerte par défaut
            'niveau_alerte'        => 'verte',

            'created_by'           => auth()->id(),
            'updated_by'           => auth()->id(),
        ]);
        $porteurProj->save();

        // ── 5. PARTENAIRES (jusqu'à 17, cols I à AO) ─────────────
        $this->importerPartenairesDeesBdd($porteurProj, $row);

        // ── 6. BÉNÉFICIAIRES PRÉVUS (AX-BC) — multi-lieu ────────
        $regionTxtDees = trim((string) $this->cell($row, 'AV'));
        $this->importerBenef($porteurProj, 'prevu', [
            'total'  => (string) $this->cell($row, 'AX'),
            'h'      => (string) $this->cell($row, 'AY'),
            'f'      => (string) $this->cell($row, 'AZ'),
            'jeunes' => (string) $this->cell($row, 'BA'),
            'fpe'    => (string) $this->cell($row, 'BB'),
            'cadres' => (string) $this->cell($row, 'BC'),
        ], $regionTxtDees);

        // ── 7. BÉNÉFICIAIRES RÉALISÉS (BP-BU) — multi-lieu ──────
        $this->importerBenef($porteurProj, 'realise', [
            'total'  => (string) $this->cell($row, 'BP'),
            'h'      => (string) $this->cell($row, 'BQ'),
            'f'      => (string) $this->cell($row, 'BR'),
            'jeunes' => (string) $this->cell($row, 'BS'),
            'fpe'    => (string) $this->cell($row, 'BT'),
            'cadres' => (string) $this->cell($row, 'BU'),
        ], $regionTxtDees);

        // ── 8. FORMATION PRÉVUE (BD-BH) ──────────────────────────
        $volTotalPrevu = min($this->parseInt($this->cell($row, 'BH')), 999999);
        $this->importerFormation($porteurProj, 'prevu',
            prestataire:  trim((string) $this->cell($row, 'BD')),
            modules:      Str::limit(trim((string) $this->cell($row, 'BE')), 500, ''),
            formateur:    trim((string) $this->cell($row, 'BG')),
            volTotal:     $volTotalPrevu,
            volParModule: min($this->parseInt($this->cell($row, 'BF')), 65000)
        );

        // ── 9. FORMATION RÉALISÉE (BV = Volume Horaire Total réalisé) ──
        $volReel = min($this->parseInt($this->cell($row, 'BV')), 999999);
        if ($volReel > 0) {
            Formation::updateOrCreate(
                ['porteur_proj_id' => $porteurProj->id, 'type' => 'realise'],
                ['volume_horaire_total' => $volReel, 'created_by' => auth()->id()]
            );
        }

        $this->imported++;
    }

    // ─── Résolution porteur format DEES_BDD ───────────────────
    protected function resoudrePorteurDeesBdd(array $row): Porteur
    {
        $nomBrut         = $this->normaliserNom((string) $this->cell($row, 'F'));
        $nomStockage     = \App\Models\ImportMapping::formaterLibelle($nomBrut) ?: $nomBrut;
        $nomNormalisee   = \App\Models\ImportMapping::normaliser($nomBrut);
        $nom             = $nomStockage;   // pour compat avec code existant plus bas

        $region  = $this->resoudreRegion((string) $this->cell($row, 'AV'));
        $secteur = $this->resoudreSecteur((string) $this->cell($row, 'A'));

        $porteur = Porteur::withTrashed()->where('raison_sociale_normalisee', $nomNormalisee)->first();

        // Le CNaPS peut contenir plusieurs matricules séparés par virgules.
        // On le tronque à 100 caractères (limite DB) — toute la chaîne est conservée.
        $cnaps = Str::limit(trim((string) $this->cell($row, 'G')), 100, '');

        $champs = [
            'cnaps'           => $cnaps,
            'nb_salaries'     => $this->parseInt($this->cell($row, 'H')),
            'responsable_nom' => Str::limit(trim((string) $this->cell($row, 'AR')), 150, ''),
            'telephone'       => Str::limit(trim((string) $this->cell($row, 'AS')), 50, ''),
            'email'           => Str::limit(trim((string) $this->cell($row, 'AT')), 150, ''),
            'adresse'         => Str::limit(trim((string) $this->cell($row, 'AU')), 300, ''),
            'region_id'       => $region?->id,
            'secteur_id'      => $secteur?->id,
        ];

        if ($porteur) {
            // Compléter uniquement les champs vides
            foreach ($champs as $k => $v) {
                if (blank($porteur->$k) && filled($v)) $porteur->$k = $v;
            }
            $porteur->save();
            return $porteur;
        }

        return Porteur::create(array_filter([
            'raison_sociale'            => Str::limit($nom, 200, ''),
            'raison_sociale_normalisee' => $nomNormalisee,
            'cnaps'          => $champs['cnaps'] ?: null,
            'nb_salaries'    => $champs['nb_salaries'] ?: 0,
            'telephone'      => $champs['telephone'] ?: null,
            'email'          => $champs['email'] ?: null,
            'adresse'        => $champs['adresse'] ?: null,
            'responsable_nom'=> $champs['responsable_nom'] ?: null,
            'region_id'      => $champs['region_id'],
            'secteur_id'     => $champs['secteur_id'],
            'created_by'     => auth()->id(),
        ], fn ($v) => $v !== null && $v !== ''));
    }

    // ─── Import des 17 partenaires (cols I à AO) ──────────────
    // Format alterné : Nom (impair) + Matricule (pair)
    // Sauf PA6 où il manque le nom : col S = Matricule PA 6 seul
    protected function importerPartenairesDeesBdd(PorteurProj $pp, array $row): void
    {
        // Mapping (col_nom, col_matricule) pour chaque PA
        $mapping = [
            ['I',  'J'],   // PA 1
            ['K',  'L'],   // PA 2
            ['M',  'N'],   // PA 3
            ['O',  'P'],   // PA 4
            ['Q',  'R'],   // PA 5
            [null, 'S'],   // PA 6 (matricule seulement)
            ['T',  'U'],   // PA 7
            ['V',  'W'],   // PA 8
            ['X',  'Y'],   // PA 9
            ['Z',  'AA'],  // PA 10
            ['AB', 'AC'],  // PA 11
            ['AD', 'AE'],  // PA 12
            ['AF', 'AG'],  // PA 13
            ['AH', 'AI'],  // PA 14
            ['AJ', 'AK'],  // PA 15
            ['AL', 'AM'],  // PA 16
            ['AN', 'AO'],  // PA 17
        ];

        foreach ($mapping as [$colNom, $colMatricule]) {
            $nom       = $colNom ? trim((string) $this->cell($row, $colNom)) : '';
            $matricule = $colMatricule ? trim((string) $this->cell($row, $colMatricule)) : '';

            if (blank($nom) && blank($matricule)) continue;
            if (blank($nom)) $nom = 'Partenaire ' . ($matricule ?: '?');

            $nomNorm = $this->normaliserNom($nom);
            if (blank($nomNorm)) continue;

            Partenaire::updateOrCreate(
                ['porteur_proj_id' => $pp->id, 'nom' => $nomNorm],
                [
                    'cnaps'       => $matricule ?: null,
                    'nb_salaries' => 0,
                ]
            );
        }
    }
}
