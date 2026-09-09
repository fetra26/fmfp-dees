<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Page de login personnalisée SEER — style Hero Immersif.
 *
 * Étend le Login Filament standard mais utilise une vue Blade custom
 * qui superpose le formulaire d'auth sur un hero visuel.
 */
class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    /** Vide → cache le titre "Connectez-vous à votre compte" de Filament */
    public function getHeading(): string | Htmlable | null
    {
        return '';
    }

    /** Vide → cache le sous-titre */
    public function getSubheading(): string | Htmlable | null
    {
        return '';
    }

    /** Vide → cache le logo/brandName au-dessus */
    public function hasLogo(): bool
    {
        return false;
    }
}
