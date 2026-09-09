<?php

namespace App\Filament\Widgets\Concerns;

use App\Services\DashboardWidgetCatalog;
use Illuminate\Support\Facades\Auth;

/**
 * Trait à appliquer sur chaque widget du dashboard.
 * Vérifie que l'utilisateur connecté a le widget dans sa config (dept ou override).
 *
 * Chaque widget doit définir sa constante SLUG (correspondant au catalogue).
 */
trait CheckDashboardVisibility
{
    /**
     * Le widget est-il visible pour l'utilisateur actuel ?
     * Cumule 2 vérifications :
     *   - Contrôle d'accès par rôle (existant, ex: canViewByRole())
     *   - Config admin (département / override utilisateur)
     */
    public static function canView(): bool
    {
        $user = Auth::user();
        if (! $user) return false;

        // 1) Vérif rôle (si le widget a une méthode canViewByRole)
        if (method_exists(static::class, 'canViewByRole')) {
            if (! static::canViewByRole()) return false;
        }

        // 2) Vérif config admin (dept + override)
        $slug = static::widgetSlug();
        if ($slug === null) return true; // widget non catalogué → toujours visible

        return $user->peutVoirWidget($slug);
    }

    /**
     * Récupère le slug du widget depuis la constante SLUG ou depuis le catalogue.
     */
    protected static function widgetSlug(): ?string
    {
        if (defined(static::class . '::SLUG')) {
            return static::SLUG;
        }
        return DashboardWidgetCatalog::slugDepuisClasse(static::class);
    }
}
