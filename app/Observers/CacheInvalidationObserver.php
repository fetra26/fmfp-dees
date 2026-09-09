<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Observer global qui invalide automatiquement les caches des widgets
 * dashboard quand un projet, porteur_proj, bénéficiaire ou paiement
 * est créé/modifié/supprimé.
 *
 * Évite d'avoir des compteurs obsolètes après import ou édition.
 */
class CacheInvalidationObserver
{
    private array $cacheKeys = [
        'widget.stats.overview',
        'widget.alertes.counts',
        'widget.bilan.financier',
        'widget.performance.beneficiaires',
        'widget.chart.statuts',
        'widget.chart.regions',
        'widget.chart.beneficiaires',
        'widget.chart.secteurs',
        'widget.chart.financement_guichet',
        'widget.chart.prevu_realise',
    ];

    public function saved(Model $model): void
    {
        $this->invaliderCaches();
    }

    public function deleted(Model $model): void
    {
        $this->invaliderCaches();
    }

    private function invaliderCaches(): void
    {
        foreach ($this->cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}
