<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Secteur extends Model
{
    protected $table = 'secteur';
    protected $fillable = ['code', 'libelle'];

    public function entreprises() { return $this->hasMany(Entreprise::class); }
    public function projets() { return $this->hasMany(Projet::class); }
}
