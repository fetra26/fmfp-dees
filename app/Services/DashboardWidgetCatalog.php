<?php

namespace App\Services;

/**
 * Catalogue centralisé des widgets du tableau de bord.
 *
 * Chaque widget a un SLUG unique (persisté en base) et un label lisible.
 * Ajouter un nouveau widget = ajouter une entrée ici + définir sa constante SLUG.
 *
 * Convention : la classe du widget définit `public const SLUG = 'xxx';`
 * pour éviter les strings magiques dans le code.
 */
class DashboardWidgetCatalog
{
    /**
     * Liste des widgets disponibles, groupés par catégorie visuelle.
     *
     * @return array<string, array<string, array{class: string, label: string, description: string, roles?: array<string>}>>
     */
    public static function all(): array
    {
        return [
            '📊 Statistiques' => [
                'alertes' => [
                    'class'       => \App\Filament\Widgets\AlertesWidget::class,
                    'label'       => 'Alertes (rouges/oranges/vertes)',
                    'description' => 'Compteurs des alertes de suivi',
                ],
                'stats_overview' => [
                    'class'       => \App\Filament\Widgets\StatsOverviewWidget::class,
                    'label'       => 'Vue d\'ensemble',
                    'description' => 'Projets actifs, porteurs, allocation cumulée',
                ],
                'bilan_financier' => [
                    'class'       => \App\Filament\Widgets\BilanFinancierWidget::class,
                    'label'       => 'Bilan financier',
                    'description' => 'Montants totaux, versements J1/J2/J3',
                ],
                'performance_benef' => [
                    'class'       => \App\Filament\Widgets\PerformanceBeneficiairesWidget::class,
                    'label'       => 'Performance bénéficiaires',
                    'description' => 'Taux de réalisation prévu vs réel',
                ],
                'paiements_attente' => [
                    'class'       => \App\Filament\Widgets\PaiementsEnAttenteWidget::class,
                    'label'       => 'Paiements en attente (DAF)',
                    'description' => 'Projets à payer — réservé DAF',
                    'roles'       => ['daf', 'equipe_daf', 'admin'],
                ],
            ],

            '📈 Graphiques' => [
                'statuts_projet' => [
                    'class'       => \App\Filament\Widgets\StatutsProjetChart::class,
                    'label'       => 'Projets par statut',
                    'description' => 'Camembert des statuts',
                ],
                'projet_par_region' => [
                    'class'       => \App\Filament\Widgets\ProjetParRegionChart::class,
                    'label'       => 'Projets par région',
                    'description' => 'Répartition géographique',
                ],
                'repartition_secteur' => [
                    'class'       => \App\Filament\Widgets\RepartitionSecteurChart::class,
                    'label'       => 'Répartition par secteur',
                    'description' => 'BTP, télécom, agriculture...',
                ],
                'financement_par_guichet' => [
                    'class'       => \App\Filament\Widgets\FinancementParGuichetChart::class,
                    'label'       => 'Financement par guichet',
                    'description' => 'RIE / PIS / EQUITE / etc.',
                ],
                'beneficiaires' => [
                    'class'       => \App\Filament\Widgets\BeneficiairesChart::class,
                    'label'       => 'Bénéficiaires (répartition)',
                    'description' => 'Hommes/Femmes/Jeunes/FPE/Cadres',
                ],
                'prevu_vs_realise' => [
                    'class'       => \App\Filament\Widgets\PrevuVsRealiseChart::class,
                    'label'       => 'Prévu vs Réalisé',
                    'description' => 'Comparaison objectifs/résultats',
                ],
            ],
        ];
    }

    /**
     * Retourne juste la table [slug => label] pour les CheckboxList.
     */
    public static function optionsPourFormulaire(): array
    {
        $options = [];
        foreach (self::all() as $category => $widgets) {
            foreach ($widgets as $slug => $widget) {
                $options[$slug] = $widget['label'];
            }
        }
        return $options;
    }

    /**
     * Retourne les options groupées par catégorie pour un rendu visuel.
     * Format : [category => [slug => label]]
     */
    public static function optionsGroupees(): array
    {
        $result = [];
        foreach (self::all() as $category => $widgets) {
            $result[$category] = [];
            foreach ($widgets as $slug => $widget) {
                $result[$category][$slug] = $widget['label'];
            }
        }
        return $result;
    }

    /**
     * Tous les slugs disponibles (utilisé comme défaut si aucun override).
     */
    public static function tousLesSlugs(): array
    {
        return array_keys(self::optionsPourFormulaire());
    }

    /**
     * Retourne la classe PHP d'un widget à partir de son slug.
     */
    public static function classeDepuisSlug(string $slug): ?string
    {
        foreach (self::all() as $widgets) {
            if (isset($widgets[$slug])) {
                return $widgets[$slug]['class'];
            }
        }
        return null;
    }

    /**
     * Retourne le slug d'un widget à partir de sa classe PHP.
     */
    public static function slugDepuisClasse(string $class): ?string
    {
        foreach (self::all() as $widgets) {
            foreach ($widgets as $slug => $widget) {
                if ($widget['class'] === $class) {
                    return $slug;
                }
            }
        }
        return null;
    }
}
