<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Génère le logo SEER en PNG, EXACTEMENT aux proportions du SVG UI (Variante 3).
 *
 * SVG UI d'origine (viewBox 200x200) :
 *   - Cercle centre (80, 80)
 *   - Anneau extérieur r=67, intérieur r=53 (épaisseur 14)
 *   - Bar chart : 4 barres dans le cercle
 *   - Manche : de (122, 122) à (180, 180), épaisseur 18
 *
 * Cette PNG applique le même dessin scale x4 (canvas 800x800) pour finesse
 * puis compresse à taille finale.
 *
 * Usage : php artisan logo:generer
 */
class GenererLogoPng extends Command
{
    protected $signature = 'logo:generer';
    protected $description = 'Génère le logo SEER en PNG (à partir des proportions du SVG UI)';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('Extension GD manquante.');
            return 1;
        }

        // Canvas final 400x400, scale interne 4x pour anti-aliasing
        $finalSize = 400;
        $scale = 4;
        $size = $finalSize * $scale;  // 1600x1600

        $im = imagecreatetruecolor($size, $size);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);
        imageantialias($im, true);

        // Couleurs SEER
        $blue    = imagecolorallocate($im, 30, 64, 175);     // #1e40af
        $blueLt  = imagecolorallocate($im, 59, 130, 246);    // #3b82f6
        $orange  = imagecolorallocate($im, 245, 158, 11);    // #f59e0b
        $purple  = imagecolorallocate($im, 124, 58, 237);    // #7c3aed
        $white   = imagecolorallocate($im, 255, 255, 255);

        // ─── Facteur de conversion viewBox (200x200) → canvas ───
        // Le contenu SVG utilise le coin haut-gauche (0-200) x (0-200)
        // On scale directement.
        $s = ($size / 200);  // 200 units = full canvas → chaque unit = 8 pixels

        // ─── MANCHE ───
        // SVG : line (122, 122) → (180, 180), stroke=18 → épaisseur 18/2 = 9 de chaque côté
        // Sur le canvas :
        $mx1 = 122 * $s;
        $my1 = 122 * $s;
        $mx2 = 180 * $s;
        $my2 = 180 * $s;
        $mancheEp = 18 * $s;

        // Vecteur perpendiculaire à la ligne (45°)
        // La ligne va à 45° donc perp = (-sin45, cos45) = (-0.7071, 0.7071)
        $perpX = -0.7071 * ($mancheEp / 2);
        $perpY =  0.7071 * ($mancheEp / 2);

        // 4 coins du rectangle-manche
        $manche = [
            $mx1 + $perpX, $my1 + $perpY,  // haut-gauche
            $mx1 - $perpX, $my1 - $perpY,  // bas-gauche
            $mx2 - $perpX, $my2 - $perpY,  // bas-droite
            $mx2 + $perpX, $my2 + $perpY,  // haut-droite
        ];
        // Convertir en int
        $manche = array_map('intval', $manche);
        imagefilledpolygon($im, $manche, $blue);

        // Ajouter des demi-cercles aux 2 extrémités du manche pour le stroke-linecap="round"
        $capRadius = intval($mancheEp / 2);
        imagefilledellipse($im, intval($mx1), intval($my1), $capRadius * 2, $capRadius * 2, $blue);
        imagefilledellipse($im, intval($mx2), intval($my2), $capRadius * 2, $capRadius * 2, $blue);

        // ─── ANNEAU (loupe) ───
        // SVG : circle r=60, stroke=14 → extérieur r=67, intérieur r=53
        // Centre (80, 80)
        $cx = intval(80 * $s);
        $cy = intval(80 * $s);
        $rExt = intval(67 * $s);
        $rInt = intval(53 * $s);

        // On dessine 2 cercles pour l'effet anneau
        imagefilledellipse($im, $cx, $cy, $rExt * 2, $rExt * 2, $blue);   // extérieur bleu
        imagefilledellipse($im, $cx, $cy, $rInt * 2, $rInt * 2, $white);  // intérieur blanc

        // ─── BAR CHART dans la loupe ───
        // SVG : rect x, y, w=12, h=variable
        // On scale
        $barW = intval(12 * $s);

        // Bar 1 : x=45, y=90, h=20 (bleu)
        imagefilledrectangle($im, intval(45 * $s), intval(90 * $s), intval(45 * $s) + $barW, intval((90 + 20) * $s), $blueLt);

        // Bar 2 : x=63, y=75, h=35 (bleu)
        imagefilledrectangle($im, intval(63 * $s), intval(75 * $s), intval(63 * $s) + $barW, intval((75 + 35) * $s), $blueLt);

        // Bar 3 : x=81, y=60, h=50 (ORANGE — accent)
        imagefilledrectangle($im, intval(81 * $s), intval(60 * $s), intval(81 * $s) + $barW, intval((60 + 50) * $s), $orange);

        // Bar 4 : x=99, y=82, h=28 (bleu)
        imagefilledrectangle($im, intval(99 * $s), intval(82 * $s), intval(99 * $s) + $barW, intval((82 + 28) * $s), $blueLt);

        // ─── LIGNE DE TENDANCE (curve orange) ───
        // SVG : M 50 90 Q 70 70, 100 55 (courbe de Bézier)
        // On approxime avec plusieurs segments
        $ligneEp = intval(3 * $s);
        $prevX = 50 * $s;
        $prevY = 90 * $s;
        for ($t = 0.05; $t <= 1.0; $t += 0.05) {
            // Bézier quadratique : B(t) = (1-t)²·P0 + 2(1-t)t·P1 + t²·P2
            $x = pow(1 - $t, 2) * (50 * $s) + 2 * (1 - $t) * $t * (70 * $s) + pow($t, 2) * (100 * $s);
            $y = pow(1 - $t, 2) * (90 * $s) + 2 * (1 - $t) * $t * (70 * $s) + pow($t, 2) * (55 * $s);

            // Dessiner un segment épais
            imagesetthickness($im, $ligneEp);
            imageline($im, intval($prevX), intval($prevY), intval($x), intval($y), $orange);

            $prevX = $x;
            $prevY = $y;
        }
        imagesetthickness($im, 1);

        // Petit point orange à la fin
        imagefilledellipse($im, intval(100 * $s), intval(55 * $s), intval(6 * $s), intval(6 * $s), $orange);

        // ─── COMPRESSION ANTI-ALIAS ───
        // On resample vers la taille finale pour un rendu propre
        $final = imagecreatetruecolor($finalSize, $finalSize);
        imagesavealpha($final, true);
        $transparentFinal = imagecolorallocatealpha($final, 0, 0, 0, 127);
        imagefill($final, 0, 0, $transparentFinal);
        imagecopyresampled($final, $im, 0, 0, 0, 0, $finalSize, $finalSize, $size, $size);
        imagedestroy($im);

        // ─── Sauvegarde ───
        $path = public_path('images/logo-seer.png');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        imagepng($final, $path);
        imagedestroy($final);

        $this->info("✔ Logo SEER généré : {$path}");
        $this->line('  Taille : ' . round(filesize($path) / 1024, 1) . ' Ko');
        $this->line('  Dimensions : ' . $finalSize . 'x' . $finalSize . ' px (avec anti-aliasing 4x)');

        return 0;
    }
}
