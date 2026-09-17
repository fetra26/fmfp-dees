<?php

namespace App\Services;

use App\Models\Region;
use App\Models\Secteur;
use Illuminate\Database\Eloquent\Builder;

/**
 * Agrégation des bénéficiaires par secteur et par région.
 *
 * Chaque chiffre est donné en prévu ET en réalisé, avec le taux d'atteinte :
 * c'est l'écart entre les deux qui renseigne le pilotage, pas l'un ou l'autre
 * pris isolément.
 *
 * ── Ce que les données permettent, et ce qu'elles ne permettent pas ──
 *
 * La table benef porte six compteurs PARALLÈLES, pas un tableau croisé :
 * total, h, f, jeunes, fpe, cadres. Il en découle deux limites structurelles,
 * qui ne sont pas des manques d'implémentation :
 *
 *   · « jeunes » est un volume sans ventilation par sexe. Savoir combien de
 *     jeunes femmes ont été formées supposerait une colonne qui n'existe ni
 *     en base, ni dans le modèle de fichier Excel.
 *   · « cadres » correspond à « Nb femmes cadres » dans le modèle d'import :
 *     le champ ne compte que des femmes, et rien n'est collecté sur les
 *     hommes cadres.
 *
 * Obtenir ces croisements demanderait d'étendre le modèle d'import et de
 * faire ressaisir la DEES — une décision métier, pas un développement.
 *
 * ── Région retenue ──
 *
 * Une ligne de bénéficiaires porte sa propre région (import multi-lieu), plus
 * précise quand un projet forme dans plusieurs régions. Quand elle manque, on
 * retombe sur la région principale du projet.
 */
class StatistiquesBeneficiaires
{
    /** Expression de la région retenue pour un bénéficiaire. */
    public const REGION = 'COALESCE(benef.region_id, projet.region_id)';

    /**
     * Colonnes agrégées, communes aux vues par secteur et par région.
     *
     * @return string[]
     */
    private static function agregats(): array
    {
        $compteurs = ['total', 'h', 'f', 'jeunes', 'fpe', 'cadres'];
        $colonnes = ["COUNT(DISTINCT porteur_proj.id) AS nb_projets"];

        foreach (['prevu', 'realise'] as $phase) {
            foreach ($compteurs as $compteur) {
                $colonnes[] = "COALESCE(SUM(CASE WHEN benef.type = '{$phase}' THEN benef.{$compteur} ELSE 0 END), 0) AS {$compteur}_{$phase}";
            }
        }

        return $colonnes;
    }

    /**
     * Bénéficiaires agrégés par secteur.
     *
     * @param  int|null  $regionId  Restreint à une région : le tableau devient
     *                              alors le croisement secteur × région.
     */
    public static function parSecteur(?int $regionId = null): Builder
    {
        $query = Secteur::query()
            ->leftJoin('projet', 'projet.secteur_id', '=', 'secteur.id')
            ->leftJoin('porteur_proj', 'porteur_proj.projet_id', '=', 'projet.id')
            ->leftJoin('benef', 'benef.porteur_proj_id', '=', 'porteur_proj.id')
            ->select('secteur.id', 'secteur.code', 'secteur.libelle')
            ->selectRaw(implode(', ', self::agregats()))
            ->groupBy('secteur.id', 'secteur.code', 'secteur.libelle');

        if ($regionId !== null) {
            $query->whereRaw(self::REGION . ' = ?', [$regionId]);
        }

        return $query;
    }

    /**
     * Bénéficiaires agrégés par région, les plus fournies d'abord.
     *
     * La jointure part de benef pour honorer la région du bénéficiaire quand
     * elle existe : rattacher les lignes à region.id par le seul projet
     * ignorerait les formations tenues hors de la région principale.
     */
    public static function parRegion(): Builder
    {
        return Region::query()
            ->leftJoin('projet', function ($join) {
                $join->on('projet.region_id', '=', 'region.id');
            })
            ->leftJoin('porteur_proj', 'porteur_proj.projet_id', '=', 'projet.id')
            ->leftJoin('benef', function ($join) {
                $join->on('benef.porteur_proj_id', '=', 'porteur_proj.id')
                    ->whereRaw('COALESCE(benef.region_id, projet.region_id) = region.id');
            })
            ->select('region.id', 'region.code', 'region.libelle')
            ->selectRaw(implode(', ', self::agregats()))
            ->groupBy('region.id', 'region.code', 'region.libelle')
            ->orderByDesc('nb_projets');
    }

    /**
     * Totaux tous secteurs et toutes régions confondus.
     *
     * @return array<string, int>
     */
    public static function global(): array
    {
        $ligne = Secteur::query()
            ->rightJoin('projet', 'projet.secteur_id', '=', 'secteur.id')
            ->leftJoin('porteur_proj', 'porteur_proj.projet_id', '=', 'projet.id')
            ->leftJoin('benef', 'benef.porteur_proj_id', '=', 'porteur_proj.id')
            ->selectRaw(implode(', ', self::agregats()))
            ->first();

        $totaux = [];
        foreach (['total', 'h', 'f', 'jeunes', 'fpe', 'cadres'] as $compteur) {
            foreach (['prevu', 'realise'] as $phase) {
                $cle = $compteur . '_' . $phase;
                $totaux[$cle] = (int) ($ligne?->{$cle} ?? 0);
            }
        }
        $totaux['nb_projets'] = (int) ($ligne?->nb_projets ?? 0);

        return $totaux;
    }

    /**
     * Taux d'atteinte en pourcentage entier.
     *
     * Renvoie null plutôt que zéro quand rien n'est prévu : un taux de 0 %
     * laisserait croire à un échec, alors qu'il n'y avait aucun objectif.
     */
    public static function tauxAtteinte(?int $prevu, ?int $realise): ?int
    {
        if (! $prevu) {
            return null;
        }

        return (int) round(($realise ?? 0) * 100 / $prevu);
    }
}
