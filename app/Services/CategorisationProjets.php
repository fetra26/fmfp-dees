<?php

namespace App\Services;

use App\Models\PorteurProj;
use Illuminate\Database\Eloquent\Builder;

/**
 * Classement des projets soumis selon la taxonomie DEES.
 *
 *   Soumis
 *   ├── Validé
 *   │   ├── Non notifié        (motif : possible problème DT)
 *   │   └── Notifié
 *   │       ├── Engagé          J1 versé — formation en cours
 *   │       ├── Clôturé         J1 + J2 versés (parfois + J3 sur les cas équité)
 *   │       └── Sans convention aucun retour porteur
 *   ├── Refusé                  par CSP ou AFD, avec motif
 *   ├── Non éligible            avec motifs
 *   └── Incomplet               dossier incomplet / attente pièces régul.
 *
 * Les catégories sont DÉRIVÉES des faits en base — statut, dates, paiements —
 * et non d'une saisie supplémentaire. Le classement suit donc toute correction
 * apportée aux données.
 *
 * Les feuilles de l'arbre sont mutuellement exclusives : un projet clôturé a
 * forcément été notifié, et serait compté deux fois si « notifié » se lisait
 * comme un simple fait. Chaque projet tombe dans l'état LE PLUS AVANCÉ atteint,
 * et les niveaux supérieurs (Validé, Notifié, Soumis) sont des sommes.
 *
 * L'unité de comptage est le porteur_proj, soit une ligne du fichier importé.
 */
class CategorisationProjets
{
    /** Feuilles de l'arbre, seules catégories réellement attribuées. */
    public const CATEGORIES = [
        'cloture'        => 'Clôturé',
        'engage'         => 'Engagé',
        'sans_convention' => 'Sans convention',
        'notifie_sans_versement' => 'Notifié, sans versement',
        'non_notifie'    => 'Non notifié',
        'refuse'         => 'Refusé',
        'non_eligible'   => 'Non éligible',
        'incomplet'      => 'Incomplet',
    ];

    /** Regroupements de l'arbre : un niveau est la somme de ses feuilles. */
    public const AGREGATS = [
        'notifie' => ['engage', 'cloture', 'sans_convention', 'notifie_sans_versement'],
        'valide'  => ['engage', 'cloture', 'sans_convention', 'notifie_sans_versement', 'non_notifie'],
    ];

    private const STATUTS_REFUSE = ['refuse'];
    private const STATUTS_NON_ELIGIBLE = ['inelig'];
    private const STATUTS_INCOMPLET = ['incomplet', 'attente_pieces_regul'];

    /** Sous-requête : la tranche demandée a-t-elle été versée ? */
    private static function trancheVersee(string $ligne): string
    {
        return "EXISTS (
            SELECT 1 FROM paiement p
            WHERE p.porteur_proj_id = porteur_proj.id
              AND p.ligne = '{$ligne}'
              AND p.is_annule = 0
              AND p.deleted_at IS NULL
        )";
    }

    /**
     * Expression SQL attribuant sa feuille à chaque projet.
     *
     * Écrite en une seule expression CASE : la cascade est exhaustive par
     * construction, chaque ligne tombant dans exactement une branche. L'égalité
     * « somme des feuilles = total soumis » est donc garantie, pas espérée.
     */
    public static function expressionSql(): string
    {
        $refuse      = "'" . implode("','", self::STATUTS_REFUSE) . "'";
        $nonEligible = "'" . implode("','", self::STATUTS_NON_ELIGIBLE) . "'";
        $incomplet   = "'" . implode("','", self::STATUTS_INCOMPLET) . "'";

        $j1 = self::trancheVersee('J1');
        $j2 = self::trancheVersee('J2');

        // L'ordre suit la force du signal : décision explicite, puis fait
        // avéré, puis valeur faible.
        return "CASE
            -- 1. Décisions explicites de rejet : elles ne s'obtiennent que par
            --    une saisie délibérée, jamais par défaut.
            WHEN statut_validation IN ({$refuse})      THEN 'refuse'
            WHEN statut_validation IN ({$nonEligible}) THEN 'non_eligible'

            -- 2. Versements : de l'argent versé est un fait, qui prime sur tout
            --    libellé de statut. Sans cette priorité, un projet payé restait
            --    classé « incomplet », statut_validation valant 'incomplet' par
            --    défaut en base ET pour toute cellule de statut vide.
            --    Clôturé exige J1 ET J2 ; le J3 n'existe que sur les cas équité
            --    et ne conditionne donc pas la clôture.
            WHEN {$j1} AND {$j2} THEN 'cloture'
            WHEN {$j1}           THEN 'engage'

            -- 3. Dossier incomplet ou en attente de pièces. Placé après les
            --    versements précisément parce que c'est la valeur par défaut.
            WHEN statut_validation IN ({$incomplet}) THEN 'incomplet'

            -- 4. Validé mais jamais notifié au porteur.
            WHEN date_notification IS NULL THEN 'non_notifie'

            -- 5. Notifié sans versement : le porteur a-t-il retourné la convention ?
            WHEN date_reception_convention IS NULL THEN 'sans_convention'

            -- 6. Convention revenue, mais pas le moindre versement : ni engagé
            --    au sens DEES, ni sans convention. Cas qu'aucune branche du
            --    schéma ne couvre, isolé plutôt que rangé d'office ailleurs.
            ELSE 'notifie_sans_versement'
        END";
    }

    /**
     * Compte les projets par feuille, en une seule requête.
     *
     * @return array<string, int>  Toutes les feuilles sont présentes, à zéro le cas échéant.
     */
    public static function compter(): array
    {
        $comptes = array_fill_keys(array_keys(self::CATEGORIES), 0);

        $lignes = PorteurProj::query()
            ->selectRaw(self::expressionSql() . ' AS categorie, COUNT(*) AS total')
            ->groupBy('categorie')
            ->pluck('total', 'categorie');

        foreach ($lignes as $categorie => $total) {
            $comptes[$categorie] = (int) $total;
        }

        return $comptes;
    }

    /**
     * Compte un niveau intermédiaire de l'arbre (notifie, valide).
     *
     * @param  array<string, int>  $comptes  Résultat de compter()
     */
    public static function agregat(array $comptes, string $niveau): int
    {
        $total = 0;
        foreach (self::AGREGATS[$niveau] ?? [] as $feuille) {
            $total += $comptes[$feuille] ?? 0;
        }

        return $total;
    }

    /** Nombre total de projets soumis, soit une ligne de fichier importée. */
    public static function totalSoumis(): int
    {
        return PorteurProj::count();
    }

    /** Restreint une requête à une feuille, pour ouvrir la liste correspondante. */
    public static function filtrer(Builder $query, string $categorie): Builder
    {
        return $query->whereRaw(self::expressionSql() . ' = ?', [$categorie]);
    }

    /** Feuille d'un projet donné, avec le même classement que les compteurs. */
    public static function categorieDe(PorteurProj $porteurProj): string
    {
        // first() et non value() : ce dernier remplace le SELECT par la seule
        // colonne demandée, ce qui écraserait l'expression CASE.
        $ligne = PorteurProj::query()
            ->whereKey($porteurProj->getKey())
            ->selectRaw(self::expressionSql() . ' AS categorie')
            ->first();

        return (string) ($ligne?->categorie ?? 'non_notifie');
    }

    /** Libellé lisible d'une feuille. */
    public static function libelle(string $categorie): string
    {
        return self::CATEGORIES[$categorie] ?? $categorie;
    }
}
