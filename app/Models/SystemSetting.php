<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Configuration système clé/valeur.
 *
 * Cache 5 min pour éviter des requêtes DB à chaque render.
 * Le cache est invalidé automatiquement quand on set() une nouvelle valeur.
 */
class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = ['cle', 'valeur', 'description'];

    protected $casts = [
        'valeur' => 'array',
    ];

    /**
     * Récupère une valeur avec fallback si non définie.
     *
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $cle, $default = null)
    {
        return Cache::remember("system_setting.{$cle}", 300, function () use ($cle, $default) {
            $setting = self::where('cle', $cle)->first();
            return $setting?->valeur ?? $default;
        });
    }

    /**
     * Définit une valeur et invalide le cache.
     */
    public static function set(string $cle, $valeur, ?string $description = null): void
    {
        self::updateOrCreate(
            ['cle' => $cle],
            ['valeur' => $valeur, 'description' => $description]
        );
        Cache::forget("system_setting.{$cle}");
    }
}
