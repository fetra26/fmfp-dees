<?php

namespace App\Models\Concerns;

/**
 * Trait pour les modèles ayant une référence textuelle qui doit être normalisée
 * (retrait espaces/tirets/underscores + majuscules) pour permettre un matching
 * souple à l'import.
 *
 * À utiliser sur les modèles Projet, Convention, PorteurProj.
 *
 * Le modèle qui l'utilise doit définir la constante :
 *   const CHAMP_REFERENCE            = 'reference';                        // colonne source
 *   const CHAMP_REFERENCE_NORMALISEE = 'reference_normalisee';             // colonne cible
 */
trait NormaliseReference
{
    /**
     * Boot : hook qui remplit automatiquement la colonne normalisée à chaque save.
     */
    public static function bootNormaliseReference(): void
    {
        static::saving(function ($model) {
            $source = defined(static::class . '::CHAMP_REFERENCE')
                ? static::CHAMP_REFERENCE
                : 'reference';

            $cible = defined(static::class . '::CHAMP_REFERENCE_NORMALISEE')
                ? static::CHAMP_REFERENCE_NORMALISEE
                : 'reference_normalisee';

            $model->{$cible} = self::normaliserReference($model->{$source} ?? null);
        });
    }

    /**
     * Normalise une référence : retire séparateurs, met en majuscules.
     *
     *   STELLARIX_2026_001  → STELLARIX2026001
     *   Stellarix-2026-001  → STELLARIX2026001
     *   stellarix 2026 001  → STELLARIX2026001
     *   STELLARIX2026001    → STELLARIX2026001
     */
    public static function normaliserReference(?string $ref): ?string
    {
        if ($ref === null) return null;
        $ref = trim($ref);
        if ($ref === '') return null;

        $ref = mb_strtoupper($ref);
        $ref = preg_replace('/[\s\-_\/\.]+/u', '', $ref);

        return $ref !== '' ? $ref : null;
    }
}
