<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commune extends Model
{
    protected $table = 'commune';

    protected $fillable = ['region_id', 'district_id', 'libelle', 'libelle_normalise'];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function fokontanys(): HasMany
    {
        return $this->hasMany(Fokontany::class);
    }
}
