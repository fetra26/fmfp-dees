<?php

namespace App\Services;

use App\Models\Guichet;
use App\Models\ImportMapping;
use App\Models\Region;
use App\Models\Secteur;
use App\Models\StatutProjet;
use App\Models\Vague;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Scanne un fichier Excel avant l'import pour détecter les valeurs de référentiels
 * inconnues et proposer des correspondances (mappings) à l'utilisateur.
 *
 *   1. Lit toutes les lignes du fichier
 *   2. Extrait les valeurs distinctes de chaque colonne référentiel (Secteur, Vague, Guichet, Statut, Région)
 *   3. Compare avec la base + mappings déjà mémorisés
 *   4. Retourne la liste des valeurs à résoudre + suggestions similaires (similar_text)
 */
class PreflightScanner
{
    /** Seuil de similarité pour proposer un mapping automatique */
    private const SEUIL_SUGGESTION = 60.0;

    /** Colonnes Excel des référentiels (aligné TEMPLATE_IMPORT_DEES actuel) */
    private const COLONNES_REFERENTIELS = [
        'secteur' => 'A',  // Ligne 4+
        'vague'   => 'B',
        'guichet' => 'C',
        'region'  => 'N',  // Région (colonne 14 du template)
        'statut'  => 'AH', // Statut (section 3)
    ];

    /** Snapshot des lignes Excel — colonnes clés (Ref projet, Porteur, Intitulé) pour aperçu utilisateur */
    protected array $snapshotLignes = [];

    /** Nombre max de lignes affichées dans l'aperçu par valeur inconnue */
    private const MAX_PREVIEW_ROWS = 20;

    /**
     * Scanne le fichier et retourne un rapport des inconnus par référentiel.
     *
     * @return array{
     *   secteur: array<string, array>,
     *   vague: array<string, array>,
     *   guichet: array<string, array>,
     *   region: array<string, array>,
     *   statut: array<string, array>,
     *   total_lignes: int,
     *   total_inconnus: int
     * }
     */
    public function scanner(string $chemin, int $startRow = 3): array
    {
        $sp = IOFactory::load($chemin);
        $sheet = $sp->getSheetByName('Import DEES') ?? $sp->getActiveSheet();
        // 2e paramètre à true : les classeurs de la DEES calculent certains
        // totaux par formule. Sans évaluation, on lirait le TEXTE de la
        // formule — « =SUM(Tableau23[[#This Row],[Homme]]...) » — dont on
        // extrayait par erreur les chiffres du nom de table.
        $lignes = $sheet->toArray(null, true, false, false);

        // 1) Extraire les valeurs distinctes par colonne
        $valeurs = [
            'secteur' => [],
            'vague'   => [],
            'guichet' => [],
            'region'  => [],
            'statut'  => [],
        ];

        // Snapshot des colonnes clés pour aperçu utilisateur (col D=Ref projet, E=Ref conv, F=Porteur, P=Intitulé)
        $snapshotLignes = [];

        for ($i = $startRow - 1; $i < count($lignes); $i++) {
            $numLigne = $i + 1;
            $row = $lignes[$i];

            foreach (self::COLONNES_REFERENTIELS as $ref => $colLetter) {
                $val = trim((string) ($row[$this->colIndex($colLetter)] ?? ''));
                if (blank($val)) continue;
                $valeurs[$ref][$val] ??= [];
                $valeurs[$ref][$val][] = $numLigne;
            }

            // Snapshot des colonnes clés de CETTE ligne (col O = Intitulé template actuel)
            $snapshotLignes[$numLigne] = [
                'ref_projet'  => trim((string) ($row[$this->colIndex('D')] ?? '')),
                'ref_conv'    => trim((string) ($row[$this->colIndex('E')] ?? '')),
                'porteur'     => trim((string) ($row[$this->colIndex('F')] ?? '')),
                'intitule'    => trim((string) ($row[$this->colIndex('O')] ?? '')),
            ];
        }

        // Rendre le snapshot dispo pour la méthode resoudre() via propriété
        $this->snapshotLignes = $snapshotLignes;

        // 2) Résoudre chaque valeur
        $result = [
            'secteur' => $this->resoudre($valeurs['secteur'], Secteur::class, 'libelle'),
            'vague'   => $this->resoudre($valeurs['vague'],   Vague::class,   'libelle'),
            'guichet' => $this->resoudre($valeurs['guichet'], Guichet::class, 'libelle'),
            'region'  => $this->resoudre($valeurs['region'],  Region::class,  'libelle', estRegion: true),
            'statut'  => $this->resoudre($valeurs['statut'],  StatutProjet::class, 'libelle'),
        ];

        // 3) Statistiques globales
        $totalInconnus = 0;
        foreach ($result as $ref => $items) {
            foreach ($items as $item) {
                if ($item['statut'] === 'inconnu') $totalInconnus++;
            }
        }

        $result['total_lignes']   = count($lignes) - ($startRow - 1);
        $result['total_inconnus'] = $totalInconnus;

        return $result;
    }

    /**
     * Pour chaque valeur d'un référentiel, indique si elle est connue, avec mapping mémorisé ou inconnue.
     *
     * REGROUPEMENT AUTOMATIQUE : les valeurs qui normalisent vers la même forme
     * sont fusionnées dans UNE seule entrée. Ex: "MULTI-EDUCATION", "MULTI EDUCATION",
     * "MULTI'EDUCATION", "Multi Éducation" → une seule carte dans le wizard.
     * La 1ère variante rencontrée sert de représentant.
     * Le champ `variantes` liste toutes les formes trouvées.
     */
    /** Seuil de similarité pour AUTO-CORRECTION des typos (VAKINAKARATRA → VAKINANKARATRA) */
    private const SEUIL_TYPO_AUTO = 85.0;

    /** Seuil taille : au-delà, la valeur est traitée comme multi-lieu à splitter */
    private const TAILLE_MULTI_LIEU_AUTO = 30;

    protected function resoudre(array $valeurs, string $modelClass, string $labelColumn, bool $estRegion = false): array
    {
        $existants = $modelClass::pluck('id', $labelColumn)->toArray();
        $existantsNormalises = [];
        foreach ($existants as $libelle => $id) {
            $existantsNormalises[ImportMapping::normaliser($libelle)] = ['id' => $id, 'libelle' => $libelle];
        }

        // NOUVEAU : Index secondaire par CODE (pour matcher BTP/RS avec code=BTP_RS)
        $existantsParCode = [];
        if (property_exists(new $modelClass(), 'attributes') || method_exists($modelClass, 'getFillable')) {
            foreach ($modelClass::query()->get(['id', 'code', $labelColumn]) as $r) {
                if (! empty($r->code)) {
                    $codeNorm = ImportMapping::normaliser($r->code);
                    if ($codeNorm) $existantsParCode[$codeNorm] = ['id' => $r->id, 'libelle' => $r->{$labelColumn}];
                }
            }
        }

        $referentiel = mb_strtolower(class_basename($modelClass));
        // Normaliser les cas particuliers
        $referentiel = str_replace('projet', '', $referentiel); // StatutProjet → statut
        $mappingsMemoires = ImportMapping::where('referentiel', $referentiel)->get()
            ->keyBy('valeur_saisie_normalisee');

        // ─── Étape 1 : GROUPER les valeurs Excel par forme normalisée ───
        // Les 4 variantes "MULTI-EDUCATION", "MULTI EDUCATION", "MULTI'EDUCATION", "MultiEdu"
        // partagent la même forme normalisée "MULTIEDUCATION" → même groupe.
        $groupes = [];  // ['MULTIEDUCATION' => ['variantes' => ['MULTI-EDU', 'multi edu', ...], 'lignes' => [4, 8, 12, 20], 'representant' => 'MULTI-EDUCATION']]
        foreach ($valeurs as $valeurSaisie => $lignes) {
            $normalisee = ImportMapping::normaliser($valeurSaisie);
            if ($normalisee === null) continue;

            if (! isset($groupes[$normalisee])) {
                $groupes[$normalisee] = [
                    'representant' => $valeurSaisie,   // 1ère variante trouvée
                    'variantes'    => [],
                    'lignes'       => [],
                ];
            }
            $groupes[$normalisee]['variantes'][$valeurSaisie] = count($lignes);
            $groupes[$normalisee]['lignes'] = array_unique(array_merge($groupes[$normalisee]['lignes'], $lignes));
        }

        // ─── Étape 2 : résoudre chaque groupe (connu / mémorisé / inconnu) ───
        $result = [];
        foreach ($groupes as $normalisee => $groupe) {
            $rep = $groupe['representant'];

            // ─── Cas 1 : matching exact avec un existant (par libellé) ───
            if (isset($existantsNormalises[$normalisee])) {
                $result[$rep] = [
                    'statut'       => 'connu',
                    'target_id'    => $existantsNormalises[$normalisee]['id'],
                    'target_label' => $existantsNormalises[$normalisee]['libelle'],
                    'lignes'       => $groupe['lignes'],
                    'variantes'    => $groupe['variantes'],
                ];
                continue;
            }

            // ─── Cas 1.b : matching par CODE (BTP/RS → BTP_RS, DR → DR, THA → THA) ───
            if (isset($existantsParCode[$normalisee])) {
                $result[$rep] = [
                    'statut'       => 'connu',
                    'target_id'    => $existantsParCode[$normalisee]['id'],
                    'target_label' => $existantsParCode[$normalisee]['libelle'],
                    'lignes'       => $groupe['lignes'],
                    'variantes'    => $groupe['variantes'],
                    'via_code'     => true,
                ];
                continue;
            }

            // ─── Cas 2 : mapping mémorisé ───
            if ($mappingsMemoires->has($normalisee)) {
                $m = $mappingsMemoires->get($normalisee);
                $result[$rep] = [
                    'statut'       => 'memorise',
                    'action'       => $m->action,
                    'target_id'    => $m->target_id,
                    'target_label' => $m->target_label,
                    'lignes'       => $groupe['lignes'],
                    'variantes'    => $groupe['variantes'],
                ];
                continue;
            }

            // ─── Cas 3 : inconnu — chercher suggestions par similarité ───
            $suggestions = [];
            foreach ($existants as $libelle => $id) {
                similar_text(mb_strtoupper($rep), mb_strtoupper($libelle), $percent);
                if ($percent >= self::SEUIL_SUGGESTION) {
                    $suggestions[] = [
                        'id'         => $id,
                        'libelle'    => $libelle,
                        'similarite' => round($percent, 1),
                    ];
                }
            }
            usort($suggestions, fn ($a, $b) => $b['similarite'] <=> $a['similarite']);
            $suggestions = array_slice($suggestions, 0, 3);

            // ─── Cas 3.b (REGION uniquement) : détection géo via commune/district/fokontany ───
            // Ex: "Ambanja" → commune de DIANA → mapping automatique vers région DIANA
            $geoMatch = null;
            if ($estRegion) {
                $geoMatch = $this->detecterViaGeographie($rep);
                if ($geoMatch !== null) {
                    // On promeut ce match comme "connu" pour importation directe
                    $result[$rep] = [
                        'statut'         => 'connu',
                        'target_id'      => $geoMatch['region_id'],
                        'target_label'   => $geoMatch['region_libelle'],
                        'lignes'         => $groupe['lignes'],
                        'variantes'      => $groupe['variantes'],
                        'via_geo'        => [
                            'type'         => $geoMatch['type'],       // commune / district / fokontany
                            'lieu_libelle' => $geoMatch['lieu_libelle'],
                        ],
                    ];
                    continue; // On passe au groupe suivant, pas besoin d'inconnu
                }
            }

            // ─── Cas 3.c (REGION uniquement) : détection MULTI-LIEU automatique ───
            // Ex: "VAKINANKARATRA / AMBANJA / MANAKARA" ou texte >30 chars ou avec séparateurs
            if ($estRegion && $this->estMultiLieu($rep)) {
                $result[$rep] = [
                    'statut'         => 'multi_lieu',
                    'target_id'      => null,
                    'target_label'   => '(sera splitté par l\'import multi-lieu)',
                    'lignes'         => $groupe['lignes'],
                    'variantes'      => $groupe['variantes'],
                    'note'           => 'Valeur multi-lieu détectée automatiquement. Chaque lieu sera résolu individuellement.',
                ];
                continue;
            }

            // ─── Cas 3.d : AUTO-CORRECTION TYPO (fuzzy match >= 85% avec un existant) ───
            // Ex: "VAKINAKARATRA" (typo) → VAKINANKARATRA (officiel)
            //     "ANTSINANA" → ATSINANANA
            //     "HAUTE MAHATSIATRA" → HAUTE MATSIATRA
            $meilleur = null;
            $meilleureSim = 0;
            foreach ($existantsNormalises as $normExistant => $info) {
                similar_text($normalisee, $normExistant, $percent);
                if ($percent > $meilleureSim) {
                    $meilleureSim = $percent;
                    $meilleur = $info;
                }
            }
            if ($meilleur && $meilleureSim >= self::SEUIL_TYPO_AUTO) {
                $result[$rep] = [
                    'statut'         => 'connu',
                    'target_id'      => $meilleur['id'],
                    'target_label'   => $meilleur['libelle'],
                    'lignes'         => $groupe['lignes'],
                    'variantes'      => $groupe['variantes'],
                    'via_typo'       => round($meilleureSim, 1),
                ];
                continue;
            }

            // Aperçu des lignes concernées (max 20) pour affichage utilisateur
            $lignesAfficher = array_slice($groupe['lignes'], 0, self::MAX_PREVIEW_ROWS);
            $previewLignes = [];
            foreach ($lignesAfficher as $numLigne) {
                if (isset($this->snapshotLignes[$numLigne])) {
                    $previewLignes[] = array_merge(
                        ['num_ligne' => $numLigne],
                        $this->snapshotLignes[$numLigne]
                    );
                }
            }

            $result[$rep] = [
                'statut'         => 'inconnu',
                'lignes'         => $groupe['lignes'],
                'nb_lignes'      => count($groupe['lignes']),
                'suggestions'    => $suggestions,
                'variantes'      => $groupe['variantes'],
                'libelle_seer'   => ImportMapping::formaterLibelle($rep),  // Norme SEER : UPPER_SNAKE_CASE
                'preview_lignes' => $previewLignes,
                'preview_max'    => self::MAX_PREVIEW_ROWS,
            ];
        }

        return $result;
    }

    /**
     * Détecte si un texte contient plusieurs lieux (multi-lieu) :
     * - Contient un séparateur explicite : /, ; | virgule, retour à la ligne
     * - Ou est très long (>30 chars) = probable description multi-lieux
     * - Ou contient plusieurs mots-clés géo type "Region", "District", "Commune"
     */
    protected function estMultiLieu(string $texte): bool
    {
        // 1) Séparateurs explicites
        foreach (['/', ';', '|', "\n"] as $sep) {
            if (str_contains($texte, $sep)) return true;
        }
        // Virgule = multi-lieu SAUF si c'est juste "ATSIMO, ANDREFANA" (une région composée)
        if (substr_count($texte, ',') >= 1) {
            // Compter si ça ressemble à une énumération (plusieurs mots séparés par virgule)
            $parts = array_filter(array_map('trim', explode(',', $texte)));
            if (count($parts) >= 2 && strlen($texte) > 20) return true;
        }
        // 2) Texte long = probable description
        if (mb_strlen($texte) > self::TAILLE_MULTI_LIEU_AUTO) return true;
        // 3) Mots-clés géographiques répétés
        $mots = ['région', 'region', 'district', 'commune', 'fokontany'];
        foreach ($mots as $m) {
            if (substr_count(mb_strtolower($texte), $m) >= 2) return true;
        }
        return false;
    }

    /**
     * Détecte si une valeur "région" saisie est en fait une commune, un district
     * ou un fokontany connu de Madagascar → retourne la région parente.
     *
     * Exemple : "Ambanja" → commune de DIANA → retourne région DIANA
     *
     * @return array{type: string, lieu_libelle: string, region_id: int, region_libelle: string}|null
     */
    protected function detecterViaGeographie(string $texte): ?array
    {
        $norm = ImportMapping::normaliser($texte);
        if ($norm === null) return null;

        // 1) Commune (le cas le plus fréquent : nom de ville)
        $commune = \DB::table('commune as c')
            ->join('region as r', 'r.id', '=', 'c.region_id')
            ->where('c.libelle_normalise', $norm)
            ->select('c.libelle as lieu', 'r.id as region_id', 'r.libelle as region_libelle')
            ->first();
        if ($commune) {
            return [
                'type'           => 'commune',
                'lieu_libelle'   => $commune->lieu,
                'region_id'      => $commune->region_id,
                'region_libelle' => $commune->region_libelle,
            ];
        }

        // 2) District
        $district = \DB::table('district as d')
            ->join('region as r', 'r.id', '=', 'd.region_id')
            ->where('d.libelle_normalise', $norm)
            ->select('d.libelle as lieu', 'r.id as region_id', 'r.libelle as region_libelle')
            ->first();
        if ($district) {
            return [
                'type'           => 'district',
                'lieu_libelle'   => $district->lieu,
                'region_id'      => $district->region_id,
                'region_libelle' => $district->region_libelle,
            ];
        }

        // 3) Fokontany (recherche la plus lente, en dernier)
        $fokontany = \DB::table('fokontany as f')
            ->join('region as r', 'r.id', '=', 'f.region_id')
            ->where('f.libelle_normalise', $norm)
            ->select('f.libelle as lieu', 'r.id as region_id', 'r.libelle as region_libelle')
            ->limit(1)
            ->first();
        if ($fokontany) {
            return [
                'type'           => 'fokontany',
                'lieu_libelle'   => $fokontany->lieu,
                'region_id'      => $fokontany->region_id,
                'region_libelle' => $fokontany->region_libelle,
            ];
        }

        return null;
    }

    /**
     * Convertit "A" → 0, "AI" → 34, etc.
     */
    protected function colIndex(string $col): int
    {
        $idx = 0;
        $col = strtoupper(trim($col));
        for ($i = 0; $i < strlen($col); $i++) {
            $idx = $idx * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $idx - 1;
    }

    /**
     * Mémorise un mapping pour les prochains imports.
     */
    public function memoriser(string $referentiel, string $valeurSaisie, string $action, ?int $targetId = null, ?string $targetLabel = null): void
    {
        ImportMapping::updateOrCreate(
            [
                'referentiel'              => $referentiel,
                'valeur_saisie_normalisee' => ImportMapping::normaliser($valeurSaisie),
            ],
            [
                'valeur_saisie' => $valeurSaisie,
                'action'        => $action,
                'target_id'     => $targetId,
                'target_label'  => $targetLabel,
                'created_by'    => auth()->id(),
            ]
        );
    }
}
