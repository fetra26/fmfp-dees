<?php

namespace Tests\Unit;

use App\Jobs\ClassifyAlertsJob;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Seuils d'alerte DEES : null sous 30 jours, verte [30-59[, orange [60-90[,
 * rouge [90+. Renvoyer null plutôt que 'verte' en deçà de 30 jours distingue
 * enfin un projet à l'heure d'un retard appelant une relance.
 *
 * Test purement unitaire : calculerNiveauAlerte() ne touche pas la base, on
 * l'appelle directement par réflexion plutôt que de faire tourner tout le job.
 */
class NiveauAlerteTest extends TestCase
{
    private function calculer(string $dateFin, string $aujourdhui): ?string
    {
        $methode = new ReflectionMethod(ClassifyAlertsJob::class, 'calculerNiveauAlerte');
        $methode->setAccessible(true);

        return $methode->invoke(new ClassifyAlertsJob(), $dateFin, Carbon::parse($aujourdhui));
    }

    public static function seuils(): array
    {
        // [jours écoulés depuis date_fin, niveau attendu, ce que ça décrit]
        return [
            'échéance dans le futur'   => [-10, null],
            'échéance aujourd’hui'     => [0,   null],
            '29 jours de retard'       => [29,  null],
            '30 jours : seuil verte'   => [30,  'verte'],
            '59 jours : dernier verte' => [59,  'verte'],
            '60 jours : bascule orange'=> [60,  'orange'],
            '89 jours : dernier orange'=> [89,  'orange'],
            '90 jours : bascule rouge' => [90,  'rouge'],
            '365 jours de retard'      => [365, 'rouge'],
        ];
    }

    #[Test]
    #[DataProvider('seuils')]
    public function il_classe_selon_les_jours_de_retard(int $joursRetard, ?string $attendu): void
    {
        $aujourdhui = '2026-06-15';
        $dateFin    = Carbon::parse($aujourdhui)->subDays($joursRetard)->toDateString();

        $this->assertSame(
            $attendu,
            $this->calculer($dateFin, $aujourdhui),
            "Avec {$joursRetard} jours de retard (fin le {$dateFin}), le niveau devrait être « {$attendu} »."
        );
    }

    #[Test]
    public function les_bascules_orange_et_rouge_sont_exactement_a_60_et_90(): void
    {
        $aujourdhui = '2026-06-15';
        $jour = fn (int $n) => Carbon::parse($aujourdhui)->subDays($n)->toDateString();

        // La veille du seuil et le seuil lui-même doivent différer : c'est ce qui
        // garantit que la borne est bien inclusive et qu'on n'a pas décalé de 1.
        $this->assertNull($this->calculer($jour(29), $aujourdhui), 'Sous 30 jours : aucune alerte.');
        $this->assertSame('verte',  $this->calculer($jour(30), $aujourdhui));
        $this->assertSame('verte',  $this->calculer($jour(59), $aujourdhui));
        $this->assertSame('orange', $this->calculer($jour(60), $aujourdhui));
        $this->assertSame('orange', $this->calculer($jour(89), $aujourdhui));
        $this->assertSame('rouge',  $this->calculer($jour(90), $aujourdhui));
    }
}
