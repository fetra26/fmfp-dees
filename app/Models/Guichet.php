<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guichet extends Model
{
    protected $table = 'guichet';
    protected $fillable = ['code', 'libelle', 'region', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function vagues() { return $this->hasMany(Vague::class); }
    public function projets() { return $this->hasMany(Projet::class); }
}
