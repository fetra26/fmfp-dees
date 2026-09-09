<?php

namespace App\Providers;

use App\Models\Benef;
use App\Models\Paiement;
use App\Models\PorteurProj;
use App\Models\Projet;
use App\Observers\CacheInvalidationObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Enregistre l'observer sur les modèles qui impactent les widgets
        // → invalidation automatique du cache lors de save/delete
        $observer = CacheInvalidationObserver::class;
        Projet::observe($observer);
        PorteurProj::observe($observer);
        Benef::observe($observer);
        Paiement::observe($observer);
    }
}
