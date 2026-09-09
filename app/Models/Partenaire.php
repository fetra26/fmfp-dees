<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partenaire extends Model
{
    protected $table = 'partenaire';
    protected $fillable = ['porteur_proj_id', 'nom', 'cnaps', 'nb_salaries', 'contact'];

    public function porteurProj() { return $this->belongsTo(PorteurProj::class); }
}
