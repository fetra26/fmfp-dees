<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = 'region';
    protected $fillable = ['code', 'libelle'];

    public function entreprises() { return $this->hasMany(Entreprise::class); }
    public function projets() { return $this->hasMany(Projet::class); }
    public function districts() { return $this->hasMany(District::class); }
    public function communes() { return $this->hasMany(Commune::class); }
    public function fokontanys() { return $this->hasMany(Fokontany::class); }
}
