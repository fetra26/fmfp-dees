<?php

namespace Tests\Feature;

use App\Models\PorteurProj;
use Database\Factories\PaiementFactory;
use Database\Factories\PorteurProjFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'allocation consommée d'un projet est la somme de ses tranches J1+J2+J3
 * non annulées. C'est ce chiffre qui dit à la DEES ce qui a réellement été
 * versé à un porteur — une erreur ici fausse tout le suivi financier.
 */
class AllocationPaiementTest extends TestCase
{
    use RefreshDatabase;

    private function porteurProj(): PorteurProj
    {
        return PorteurProjFactory::new()->create();
    }

    #[Test]
    public function elle_additionne_les_trois_tranches(): void
    {
        $pp = $this->porteurProj();

        PaiementFactory::new()->tranche('J1', 3_000_000)->create(['porteur_proj_id' => $pp->id]);
        PaiementFactory::new()->tranche('J2', 2_500_000)->create(['porteur_proj_id' => $pp->id]);
        PaiementFactory::new()->tranche('J3', 1_500_000)->create(['porteur_proj_id' => $pp->id]);

        $this->assertSame(7_000_000, (int) $pp->fresh()->allocation_consommee);
    }

    #[Test]
    public function elle_vaut_zero_sans_aucun_paiement(): void
    {
        $this->assertSame(0, (int) $this->porteurProj()->allocation_consommee);
    }

    #[Test]
    public function elle_exclut_les_tranches_annulees(): void
    {
        $pp = $this->porteurProj();

        PaiementFactory::new()->tranche('J1', 4_000_000)->create(['porteur_proj_id' => $pp->id]);
        PaiementFactory::new()->tranche('J2', 9_000_000)->annule()->create(['porteur_proj_id' => $pp->id]);

        // Une tranche annulée reste en base pour la traçabilité, mais ne doit
        // jamais entrer dans le montant consommé.
        $this->assertSame(4_000_000, (int) $pp->fresh()->allocation_consommee);
    }

    #[Test]
    public function une_tranche_annulee_reste_visible_en_base(): void
    {
        $pp = $this->porteurProj();
        PaiementFactory::new()->tranche('J1', 5_000_000)->annule()->create(['porteur_proj_id' => $pp->id]);

        $this->assertSame(1, $pp->paiements()->count());
        $this->assertSame(0, (int) $pp->fresh()->allocation_consommee);
    }

    #[Test]
    public function elle_exclut_les_paiements_supprimes(): void
    {
        $pp = $this->porteurProj();

        PaiementFactory::new()->tranche('J1', 2_000_000)->create(['porteur_proj_id' => $pp->id]);
        $aSupprimer = PaiementFactory::new()->tranche('J2', 8_000_000)->create(['porteur_proj_id' => $pp->id]);

        $aSupprimer->delete(); // soft delete

        $this->assertSame(2_000_000, (int) $pp->fresh()->allocation_consommee);
    }

    #[Test]
    public function elle_ne_melange_pas_les_paiements_de_deux_projets(): void
    {
        $a = $this->porteurProj();
        $b = $this->porteurProj();

        PaiementFactory::new()->tranche('J1', 1_000_000)->create(['porteur_proj_id' => $a->id]);
        PaiementFactory::new()->tranche('J1', 6_000_000)->create(['porteur_proj_id' => $b->id]);

        $this->assertSame(1_000_000, (int) $a->fresh()->allocation_consommee);
        $this->assertSame(6_000_000, (int) $b->fresh()->allocation_consommee);
    }

    #[Test]
    public function la_reference_de_convention_est_normalisee_a_l_enregistrement(): void
    {
        $pp = PorteurProjFactory::new()->create(['reference_convention' => 'Conv-2026/014']);

        // Le trait NormaliseReference remplit la colonne normalisée via un hook
        // « saving » : c'est elle qui sert au rapprochement à l'import.
        $this->assertSame('CONV2026014', $pp->fresh()->reference_convention_normalisee);
    }

    #[Test]
    public function la_reference_normalisee_suit_les_modifications(): void
    {
        $pp = PorteurProjFactory::new()->create(['reference_convention' => 'A-1']);
        $pp->update(['reference_convention' => 'B_2']);

        $this->assertSame('B2', $pp->fresh()->reference_convention_normalisee);
    }
}
