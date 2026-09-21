<?php

namespace Tests\Unit;

use App\Services\CalculAlerte;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Seuils d'alerte DEES, selon les jours écoulés depuis la date de fin :
 *
 *   null     moins de 30 jours, ou échéance à venir
 *   verte    30 à 59 jours
 *   orange   60 à 89 jours
 *   rouge    90 jours et plus
 *
 * Renvoyer null plutôt que 'verte' en deçà de 30 jours est ce qui distingue un
 * projet à l'heure d'un retard appelant une relance. Tant que la colonne était
 * NOT NULL avec 'verte' par défaut, les deux portaient la même valeur, et le
 * compteur « alertes vertes » du tableau de bord affichait en réalité tout le
 * portefeuille.
 *
 * Test purement unitaire : le calcul ne touche pas la base.
 */
class NiveauAlerteTest extends TestCase
{
    private function calculer(int $joursRetard, string $aujourdhui = '2026-06-15'): ?string
    {
        $dateFin = Carbon::parse($aujourdhui)->subDays($joursRetard)->toDateString();

        return CalculAlerte::niveauSelonRetard($dateFin, Carbon::parse($aujourdhui));
    }

    public static function seuils(): array
    {
        // [jours écoulés depuis date_fin, niveau attendu]
        return [
            'échéance dans le futur'    => [-10, null],
            'échéance aujourd’hui'      => [0,   null],
            '29 jours de retard'        => [29,  null],
            '30 jours : seuil verte'    => [30,  'verte'],
            '59 jours : dernier verte'  => [59,  'verte'],
            '60 jours : bascule orange' => [60,  'orange'],
            '89 jours : dernier orange' => [89,  'orange'],
            '90 jours : bascule rouge'  => [90,  'rouge'],
            '365 jours de retard'       => [365, 'rouge'],
        ];
    }

    #[Test]
    #[DataProvider('seuils')]
    public function il_classe_selon_les_jours_de_retard(int $joursRetard, ?string $attendu): void
    {
        $this->assertSame(
            $attendu,
            $this->calculer($joursRetard),
            "Avec {$joursRetard} jours de retard, le niveau devrait être "
            . ($attendu ?? 'null (aucune alerte)') . '.'
        );
    }

    #[Test]
    public function les_bascules_sont_exactement_a_30_60_et_90(): void
    {
        // La veille du seuil et le seuil lui-même doivent différer : c'est ce
        // qui garantit que la borne est inclusive et qu'on n'a pas décalé de 1.
        $this->assertNull($this->calculer(29));
        $this->assertSame('verte', $this->calculer(30));

        $this->assertSame('verte', $this->calculer(59));
        $this->assertSame('orange', $this->calculer(60));

        $this->assertSame('orange', $this->calculer(89));
        $this->assertSame('rouge', $this->calculer(90));
    }

    #[Test]
    public function une_date_de_fin_absente_ne_produit_aucune_alerte(): void
    {
        $this->assertNull(CalculAlerte::niveauSelonRetard(null));
        $this->assertNull(CalculAlerte::niveauSelonRetard(''));
    }
}
