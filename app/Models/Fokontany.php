<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fokontany extends Model
{
    protected $table = 'fokontany';

    protected $fillable = ['commune_id', 'district_id', 'region_id', 'libelle', 'libelle_normalise'];

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
