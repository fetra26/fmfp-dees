<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Benef extends Model
{
    protected $table = 'benef';
    protected $fillable = [
        'porteur_proj_id', 'type',
        'region_id', 'commune_id',
        'total', 'h', 'f', 'jeunes', 'fpe', 'cadres',
        'source',
    ];

    public const TYPE_PREVU   = 'prevu';
    public const TYPE_REALISE = 'realise';

    public const SOURCE_EXCEL_PRECIS       = 'excel_precis';        // per-lieu dans Excel
    public const SOURCE_EXCEL_REPARTI_AUTO = 'excel_reparti_auto';  // total unique divisé auto
    public const SOURCE_SAISIE_MANUELLE    = 'saisie_manuelle';     // corrigé/entré dans l'app

    public function porteurProj(): BelongsTo
    {
        return $this->belongsTo(PorteurProj::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function scopePrevu($q) { return $q->where('type', self::TYPE_PREVU); }
    public function scopeRealise($q) { return $q->where('type', self::TYPE_REALISE); }
}
