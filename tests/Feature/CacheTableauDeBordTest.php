<?php

namespace Tests\Feature;

use App\Services\CacheTableauDeBord;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Invalidation des compteurs du tableau de bord.
 *
 * Les widgets mémorisent leurs chiffres 5 minutes. Après un import ou une
 * purge, le tableau de bord continuait donc d'afficher les anciens totaux
 * pendant tout ce délai, donnant l'impression que l'opération n'avait rien
 * fait — constaté après une purge où les projets supprimés restaient visibles.
 */
class CacheTableauDeBordTest extends TestCase
{
    #[Test]
    public function toutes_les_cles_memorisees_par_les_widgets_sont_recensees(): void
    {
        // Garde-fou : ajouter un widget sans déclarer sa clé le laisserait
        // afficher des chiffres périmés après chaque import, sans que personne
        // ne fasse le lien.
        $clesTrouvees = [];

        foreach (Finder::create()->files()->in(app_path('Filament/Widgets'))->name('*.php') as $fichier) {
            preg_match_all(
                "/Cache::remember\(\s*'(widget\.[^']+)'/",
                $fichier->getContents(),
                $correspondances
            );
            $clesTrouvees = array_merge($clesTrouvees, $correspondances[1]);
        }

        $clesTrouvees = array_values(array_unique($clesTrouvees));
        $oubliees = array_diff($clesTrouvees, CacheTableauDeBord::CLES);

        $this->assertSame(
            [],
            array_values($oubliees),
            "Ces clés sont mémorisées par un widget mais absentes de CacheTableauDeBord::CLES :\n  - "
            . implode("\n  - ", $oubliees)
        );
    }

    #[Test]
    public function aucune_cle_recensee_n_est_devenue_obsolete(): void
    {
        $clesTrouvees = [];

        foreach (Finder::create()->files()->in(app_path('Filament/Widgets'))->name('*.php') as $fichier) {
            preg_match_all("/Cache::remember\(\s*'(widget\.[^']+)'/", $fichier->getContents(), $c);
            $clesTrouvees = array_merge($clesTrouvees, $c[1]);
        }

        $fantomes = array_diff(CacheTableauDeBord::CLES, array_unique($clesTrouvees));

        $this->assertSame(
            [],
            array_values($fantomes),
            'Ces clés sont recensées mais plus utilisées : ' . implode(', ', $fantomes)
        );
    }

    #[Test]
    public function vider_oublie_effectivement_les_compteurs(): void
    {
        foreach (CacheTableauDeBord::CLES as $cle) {
            Cache::put($cle, 'valeur perimee', 300);
        }

        CacheTableauDeBord::vider();

        foreach (CacheTableauDeBord::CLES as $cle) {
            $this->assertNull(Cache::get($cle), "La clé « {$cle} » aurait dû être oubliée.");
        }
    }

    #[Test]
    public function vider_ne_touche_pas_aux_autres_entrees_du_cache(): void
    {
        // La purge doit rester chirurgicale : vider tout le cache emporterait
        // aussi les permissions Spatie et les caches de configuration.
        Cache::put('spatie.permission.cache', 'a garder', 300);

        CacheTableauDeBord::vider();

        $this->assertSame('a garder', Cache::get('spatie.permission.cache'));
    }
}
