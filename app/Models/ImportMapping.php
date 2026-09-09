<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mémoire des mappings d'import : "AGRO ALIMENTAIRE" → "AGROALIMENTAIRE" (ID 3).
 *
 * Servi par PreflightScanner : si un mapping existe déjà pour une valeur inconnue,
 * on l'applique automatiquement sans redemander à l'utilisateur.
 */
class ImportMapping extends Model
{
    protected $table = 'import_mapping';

    protected $fillable = [
        'referentiel', 'valeur_saisie', 'valeur_saisie_normalisee',
        'action', 'target_id', 'target_label', 'created_by',
    ];

    /**
     * Normalise une valeur pour matching robuste et ultra-permissif.
     *
     * Retire :
     *   - Accents (É→E, Ç→C, À→A, etc.)
     *   - TOUS les caractères non-alphanumériques (espaces, tirets, apostrophes,
     *     underscores, slashes, points, parenthèses, guillemets, virgules,
     *     esperluettes, etc.)
     *   - Casse (tout en majuscules)
     *
     * Exemples :
     *   "Agro Alimentaire"      → "AGROALIMENTAIRE"
     *   "AP-1"                  → "AP1"
     *   "MULTI'EDUCATION"       → "MULTIEDUCATION"
     *   "MULTI-ÉDUCATION"       → "MULTIEDUCATION"
     *   "MULTI (EDUCATION)"     → "MULTIEDUCATION"
     *   "L'ÉLEVAGE & PÊCHE"     → "LELEVAGEPECHE"
     */
    public static function normaliser(?string $valeur): ?string
    {
        if (blank($valeur)) return null;
        $valeur = trim(mb_strtoupper($valeur));

        // ─── Retirer accents et diacritiques ───
        $valeur = strtr($valeur, [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Œ' => 'OE',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y', 'Ÿ' => 'Y',
            'Ç' => 'C', 'Ñ' => 'N',
            // Version minuscules aussi au cas où (avant mb_strtoupper serait passé à côté d'une locale spéciale)
            'à' => 'A', 'á' => 'A', 'â' => 'A', 'ã' => 'A', 'ä' => 'A', 'å' => 'A', 'æ' => 'AE',
            'é' => 'E', 'è' => 'E', 'ê' => 'E', 'ë' => 'E',
            'í' => 'I', 'ì' => 'I', 'î' => 'I', 'ï' => 'I',
            'ó' => 'O', 'ò' => 'O', 'ô' => 'O', 'õ' => 'O', 'ö' => 'O', 'ø' => 'O', 'œ' => 'OE',
            'ú' => 'U', 'ù' => 'U', 'û' => 'U', 'ü' => 'U',
            'ý' => 'Y', 'ÿ' => 'Y',
            'ç' => 'C', 'ñ' => 'N',
        ]);

        // ─── Retirer TOUT sauf A-Z et 0-9 ───
        // Retire espaces, tirets, apostrophes, guillemets, parenthèses, points,
        // slashes, virgules, esperluettes, symboles Unicode divers, etc.
        $valeur = preg_replace('/[^A-Z0-9]/u', '', $valeur);

        return $valeur !== '' ? $valeur : null;
    }

    /**
     * Formate une valeur au format « libellé SEER » : UPPER_SNAKE_CASE.
     *
     * C'est la forme officielle pour créer un nouveau référentiel.
     * Séparateurs de mots (espace, tiret, apostrophe, etc.) remplacés par underscore.
     * Accents retirés. Tout en MAJUSCULES.
     *
     * Exemples :
     *   "Multi Éducation"       → "MULTI_EDUCATION"
     *   "Artisanat DR"          → "ARTISANAT_DR"
     *   "L'Élevage & Pêche"     → "L_ELEVAGE_PECHE"
     *   "STELLARIX_2026_001"    → "STELLARIX_2026_001"
     *   "MULTIEDUCATION"        → "MULTIEDUCATION"  (pas de séparateur → tel quel)
     *   "  BTP-RS  "            → "BTP_RS"
     */
    public static function formaterLibelle(?string $valeur): ?string
    {
        if (blank($valeur)) return null;
        $valeur = trim(mb_strtoupper($valeur));

        // ─── Retirer accents ───
        $valeur = strtr($valeur, [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Œ' => 'OE',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y', 'Ÿ' => 'Y',
            'Ç' => 'C', 'Ñ' => 'N',
            'à' => 'A', 'á' => 'A', 'â' => 'A', 'ã' => 'A', 'ä' => 'A', 'å' => 'A', 'æ' => 'AE',
            'é' => 'E', 'è' => 'E', 'ê' => 'E', 'ë' => 'E',
            'í' => 'I', 'ì' => 'I', 'î' => 'I', 'ï' => 'I',
            'ó' => 'O', 'ò' => 'O', 'ô' => 'O', 'õ' => 'O', 'ö' => 'O', 'ø' => 'O', 'œ' => 'OE',
            'ú' => 'U', 'ù' => 'U', 'û' => 'U', 'ü' => 'U',
            'ý' => 'Y', 'ÿ' => 'Y',
            'ç' => 'C', 'ñ' => 'N',
        ]);

        // ─── Remplacer tout ce qui n'est pas [A-Z0-9] par _ ───
        $valeur = preg_replace('/[^A-Z0-9]+/u', '_', $valeur);

        // ─── Nettoyer les underscores en début/fin ───
        $valeur = trim($valeur, '_');

        return $valeur !== '' ? $valeur : null;
    }
}
