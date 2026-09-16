<?php

namespace App\Services;

use App\Models\PorteurProj;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Répartition des projets soumis selon leur état d'avancement.
 *
 * Règle DEES : soumis = notifié + engagé + refusé + annulé + clôturé.
 *
 * Pour que cette égalité tienne, les catégories doivent être mutuellement
 * exclusives : un projet clôturé porte forcément une date de notification, et
 * serait compté deux fois si « notifié » signifiait simplement « a une date de
 * notification ». Chaque projet est donc classé dans l'état LE PLUS AVANCÉ
 * qu'il a atteint, par une cascade descendante.
 *
 * L'unité de comptage est le porteur_proj, soit une ligne du fichier Excel :
 * un même projet porté par deux entreprises compte pour deux, conformément à
 * la lecture de la DEES.
 *
 * La catégorie n'est stockée nulle part : elle est dérivée des faits présents
 * en base — dates, montants, situation d'allocation. Aucune saisie
 * supplémentaire n'est demandée à la DEES, et la répartition suit
 * automatiquement toute correction apportée aux données.
 */
class CategorisationProjets
{
    /** Catégories, de la plus avancée à la moins avancée. L'ordre fait la cascade. */
    public const CATEGORIES = [
        'cloture'     => 'Clôturé',
        'annule'      => 'Annulé',
        'refuse'      => 'Refusé',
        'engage'      => 'Engagé',
        'notifie'     => 'Notifié',
        'soumis_seul' => 'Soumis sans suite',
    ];

    /** Statuts valant clôture. */
    private const STATUTS_CLOTURE = ['cloture', 'fini_cloture'];

    /** Statuts valant annulation. */
    private const STATUTS_ANNULE = ['annule', 'resilie'];

    /** Statuts valant refus. */
    private const STATUTS_REFUSE = ['refuse', 'inelig'];

    /**
     * Expression SQL qui attribue sa catégorie à chaque ligne.
     *
     * Écrite en une seule expression CASE plutôt qu'en requêtes séparées : la
     * cascade est ainsi exhaustive par construction, chaque ligne tombant dans
     * exactement une branche. L'égalité « somme des catégories = total soumis »
     * est donc garantie, et non simplement espérée.
     */
    public static function expressionSql(): string
    {
        $cloture = "'" . implode("','", self::STATUTS_CLOTURE) . "'";
        $annule  = "'" . implode("','", self::STATUTS_ANNULE) . "'";
        $refuse  = "'" . implode("','", self::STATUTS_REFUSE) . "'";

        return "CASE
            WHEN statut_validation IN ({$cloture}) THEN 'cloture'
            WHEN statut_validation IN ({$annule})
                 OR date_resiliation IS NOT NULL
                 OR situation_alloc = 'annule' THEN 'annule'
            WHEN statut_validation IN ({$refuse}) THEN 'refuse'
            WHEN COALESCE(montant_total, 0) > 0
                 OR COALESCE(financement_demande, 0) > 0 THEN 'engage'
            WHEN date_notification IS NOT NULL THEN 'notifie'
            ELSE 'soumis_seul'
        END";
    }

    /**
     * Compte les projets par catégorie, en une seule requête.
     *
     * @return array<string, int>  Toutes les catégories sont présentes, à zéro le cas échéant.
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

    /** Nombre total de projets soumis, soit une ligne de fichier importée. */
    public static function totalSoumis(): int
    {
        return PorteurProj::count();
    }

    /**
     * Restreint une requête à une catégorie.
     *
     * Permet d'ouvrir la liste des projets concernés depuis le tableau de bord,
     * en s'appuyant sur la même définition que les compteurs.
     */
    public static function filtrer(Builder $query, string $categorie): Builder
    {
        return $query->whereRaw(self::expressionSql() . ' = ?', [$categorie]);
    }

    /** Catégorie d'un projet donné, avec le même classement que les compteurs. */
    public static function categorieDe(PorteurProj $porteurProj): string
    {
        // On passe par first() et non value() : ce dernier remplace le SELECT
        // par la seule colonne demandée, ce qui écraserait l'expression CASE.
        $ligne = PorteurProj::query()
            ->whereKey($porteurProj->getKey())
            ->selectRaw(self::expressionSql() . ' AS categorie')
            ->first();

        return (string) ($ligne?->categorie ?? 'soumis_seul');
    }

    /** Libellé lisible d'une catégorie. */
    public static function libelle(string $categorie): string
    {
        return self::CATEGORIES[$categorie] ?? $categorie;
    }
}
