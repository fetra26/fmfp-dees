<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatutProjet extends Model
{
    protected $table = 'statut_projet';
    protected $fillable = ['code', 'libelle', 'ordre'];

    public function projets() { return $this->hasMany(Projet::class); }
}
