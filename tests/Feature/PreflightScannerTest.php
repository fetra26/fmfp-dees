<?php

namespace Tests\Feature;

use App\Models\ImportMapping;
use App\Models\Secteur;
use App\Services\PreflightScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Résolution des valeurs de référentiels avant import.
 *
 * Pour chaque valeur trouvée dans le fichier Excel, le scanner doit dire si
 * elle est déjà connue, si un mapping a été mémorisé lors d'un import
 * précédent, ou si elle est réellement inconnue et doit être soumise à
 * l'utilisateur. Une erreur de classement, et on crée des doublons de
 * référentiels ou on interroge l'utilisateur pour rien.
 */
class PreflightScannerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, int[]>  $valeurs  valeur saisie => numéros de ligne
     */
    private function resoudre(array $valeurs): array
    {
        $m = new ReflectionMethod(PreflightScanner::class, 'resoudre');
        $m->setAccessible(true);

        return $m->invoke(new PreflightScanner(), $valeurs, Secteur::class, 'libelle');
    }

    #[Test]
    public function une_valeur_identique_a_un_referentiel_est_connue(): void
    {
        $secteur = Secteur::create(['code' => 'AGRO', 'libelle' => 'AGROALIMENTAIRE']);

        $res = $this->resoudre(['AGROALIMENTAIRE' => [4]]);

        $this->assertSame('connu', $res['AGROALIMENTAIRE']['statut']);
        $this->assertSame($secteur->id, $res['AGROALIMENTAIRE']['target_id']);
    }

    #[Test]
    public function une_graphie_differente_du_meme_referentiel_est_connue(): void
    {
        $secteur = Secteur::create(['code' => 'AGRO', 'libelle' => 'AGROALIMENTAIRE']);

        // L'utilisateur a tapé « Agro-Alimentaire » : même forme normalisée,
        // donc reconnu sans rien demander.
        $res = $this->resoudre(['Agro-Alimentaire' => [4]]);

        $this->assertSame('connu', $res['Agro-Alimentaire']['statut']);
        $this->assertSame($secteur->id, $res['Agro-Alimentaire']['target_id']);
    }

    #[Test]
    public function une_valeur_peut_etre_reconnue_par_son_code(): void
    {
        $secteur = Secteur::create(['code' => 'BTP_RS', 'libelle' => 'BATIMENT ET ROUTE']);

        // Le fichier porte « BTP/RS », qui normalise en BTPRS comme le code.
        $res = $this->resoudre(['BTP/RS' => [7]]);

        $this->assertSame('connu', $res['BTP/RS']['statut']);
        $this->assertSame($secteur->id, $res['BTP/RS']['target_id']);
        $this->assertTrue($res['BTP/RS']['via_code'] ?? false);
    }

    #[Test]
    public function les_variantes_sont_regroupees_en_une_seule_entree(): void
    {
        $res = $this->resoudre([
            'MULTI-EDUCATION' => [4, 5],
            'MULTI EDUCATION' => [8],
            "MULTI'EDUCATION" => [12],
        ]);

        // Une seule carte à traiter dans le wizard, pas trois.
        $this->assertCount(1, $res);

        $entree = reset($res);
        $this->assertSame('inconnu', $entree['statut']);
        $this->assertCount(3, $entree['variantes']);
        $this->assertEqualsCanonicalizing([4, 5, 8, 12], $entree['lignes']);
    }

    #[Test]
    public function la_premiere_variante_rencontree_represente_le_groupe(): void
    {
        $res = $this->resoudre([
            'MULTI EDUCATION' => [4],
            'MULTI-EDUCATION' => [9],
        ]);

        $this->assertArrayHasKey('MULTI EDUCATION', $res);
    }

    #[Test]
    public function un_mapping_memorise_est_reapplique_sans_redemander(): void
    {
        $secteur = Secteur::create(['code' => 'AGRO', 'libelle' => 'AGROALIMENTAIRE']);

        ImportMapping::create([
            'referentiel'              => 'secteur',
            'valeur_saisie'            => 'AGRO ALIM',
            'valeur_saisie_normalisee' => ImportMapping::normaliser('AGRO ALIM'),
            'action'                   => 'map',
            'target_id'                => $secteur->id,
            'target_label'             => 'AGROALIMENTAIRE',
        ]);

        $res = $this->resoudre(['AGRO ALIM' => [4]]);

        $this->assertSame('memorise', $res['AGRO ALIM']['statut']);
        $this->assertSame('map', $res['AGRO ALIM']['action']);
        $this->assertSame($secteur->id, $res['AGRO ALIM']['target_id']);
    }

    #[Test]
    public function une_valeur_vraiment_nouvelle_est_signalee_inconnue(): void
    {
        Secteur::create(['code' => 'AGRO', 'libelle' => 'AGROALIMENTAIRE']);

        $res = $this->resoudre(['PECHE MARITIME' => [4]]);

        $this->assertSame('inconnu', $res['PECHE MARITIME']['statut']);
    }

    #[Test]
    public function une_typo_tres_proche_est_corrigee_automatiquement(): void
    {
        $secteur = Secteur::create(['code' => 'AGRO', 'libelle' => 'AGROALIMENTAIRE']);

        // 96,6 % de similarité : au-delà du seuil de 85 %, le scanner corrige
        // seul plutôt que d'ennuyer l'utilisateur avec une évidence.
        $res = $this->resoudre(['AGROALIMENTAIR' => [4]]);
        $entree = $res['AGROALIMENTAIR'];

        $this->assertSame('connu', $entree['statut']);
        $this->assertSame($secteur->id, $entree['target_id']);
        $this->assertGreaterThanOrEqual(85, $entree['via_typo'] ?? 0);
    }

    #[Test]
    public function une_valeur_moyennement_proche_reste_soumise_a_l_utilisateur(): void
    {
        $secteur = Secteur::create(['code' => 'AGRO', 'libelle' => 'AGROALIMENTAIRE']);

        // 84,6 % : juste SOUS le seuil d'auto-correction. « ALIMENTAIRE » peut
        // légitimement être un secteur distinct d'« AGROALIMENTAIRE », donc le
        // scanner propose sans trancher. C'est la frontière à ne pas déplacer
        // sans y réfléchir : au-dessus, on fusionnerait des secteurs distincts.
        $res = $this->resoudre(['ALIMENTAIRE' => [4]]);
        $entree = $res['ALIMENTAIRE'];

        $this->assertSame('inconnu', $entree['statut']);
        $this->assertNotEmpty($entree['suggestions'] ?? []);
        $this->assertSame($secteur->id, $entree['suggestions'][0]['id']);
    }

    #[Test]
    public function une_valeur_inconnue_propose_un_libelle_au_format_seer(): void
    {
        $res = $this->resoudre(['Peche Maritime' => [4]]);

        // Le wizard propose ce libellé si l'utilisateur choisit de créer le
        // référentiel : forme officielle UPPER_SNAKE_CASE.
        $this->assertSame('PECHE_MARITIME', $res['Peche Maritime']['libelle_seer']);
    }

    #[Test]
    public function une_valeur_sans_rapport_ne_recoit_pas_de_suggestion(): void
    {
        Secteur::create(['code' => 'AGRO', 'libelle' => 'AGROALIMENTAIRE']);

        $res = $this->resoudre(['XYZ' => [4]]);

        $this->assertEmpty($res['XYZ']['suggestions'] ?? []);
    }

    #[Test]
    public function les_valeurs_vides_sont_ignorees(): void
    {
        $res = $this->resoudre([
            '   '  => [4],
            '---'  => [5],
        ]);

        $this->assertSame([], $res, 'Une cellule sans contenu utile ne doit rien produire.');
    }

    #[Test]
    public function memoriser_enregistre_un_mapping_reutilisable(): void
    {
        $secteur = Secteur::create(['code' => 'AGRO', 'libelle' => 'AGROALIMENTAIRE']);

        (new PreflightScanner())->memoriser(
            'secteur', 'AGRO ALIM', 'map', $secteur->id, 'AGROALIMENTAIRE'
        );

        // Le mapping tout juste mémorisé doit être repris au scan suivant.
        $res = $this->resoudre(['AGRO ALIM' => [4]]);

        $this->assertSame('memorise', $res['AGRO ALIM']['statut']);
    }
}
