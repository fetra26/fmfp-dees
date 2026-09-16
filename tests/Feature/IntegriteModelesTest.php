<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Garde-fou contre les modèles orphelins.
 *
 * Un modèle qui pointe vers une table inexistante ne provoque aucune erreur
 * tant que personne ne l'utilise : il attend silencieusement, et casse le jour
 * où quelqu'un s'en sert. C'est exactement ce qui était arrivé à
 * App\Models\ProjectEvaluator, dont la table n'a jamais été créée par aucune
 * migration.
 */
class IntegriteModelesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<class-string<Model>> */
    private function modeles(): array
    {
        $classes = [];

        // chr(92) = antislash. Le construire ainsi évite les pièges
        // d'échappement dans un nom de classe pleinement qualifié.
        $sep = chr(92);

        // depth(0) : on ignore le sous-dossier Concerns, qui ne contient que des traits.
        foreach (Finder::create()->files()->in(app_path('Models'))->depth(0)->name('*.php') as $fichier) {
            /** @var SplFileInfo $fichier */
            $classe = 'App' . $sep . 'Models' . $sep . $fichier->getBasename('.php');

            if (! class_exists($classe)) {
                continue;
            }

            $reflexion = new ReflectionClass($classe);

            if ($reflexion->isAbstract() || ! $reflexion->isSubclassOf(Model::class)) {
                continue;
            }

            $classes[] = $classe;
        }

        sort($classes);

        return $classes;
    }

    #[Test]
    public function chaque_modele_pointe_vers_une_table_existante(): void
    {
        $modeles = $this->modeles();

        $this->assertNotEmpty($modeles, 'Aucun modèle trouvé : le test ne vérifie rien.');

        $orphelins = [];

        foreach ($modeles as $classe) {
            $table = (new $classe())->getTable();

            if (! Schema::hasTable($table)) {
                $orphelins[] = "{$classe} → table « {$table} »";
            }
        }

        $this->assertSame(
            [],
            $orphelins,
            "Ces modèles pointent vers une table qui n'existe pas. Soit la migration "
            . "manque, soit le modèle est du code mort à supprimer :\n  - "
            . implode("\n  - ", $orphelins)
        );
    }

    #[Test]
    public function chaque_modele_peut_etre_interroge_sans_erreur_sql(): void
    {
        $enEchec = [];

        foreach ($this->modeles() as $classe) {
            try {
                $classe::query()->limit(1)->get();
            } catch (\Throwable $e) {
                $enEchec[] = "{$classe} : " . $e->getMessage();
            }
        }

        $this->assertSame([], $enEchec, implode("\n", $enEchec));
    }
}
