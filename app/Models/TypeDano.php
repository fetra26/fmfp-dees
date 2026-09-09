<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeDano extends Model
{
    protected $table = 'type_dano';
    protected $fillable = ['code', 'libelle'];

    public function danos() { return $this->hasMany(Dano::class); }
}
