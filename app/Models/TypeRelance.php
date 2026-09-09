<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeRelance extends Model
{
    protected $table = 'type_relance';
    protected $fillable = ['code', 'libelle'];

    public function relances() { return $this->hasMany(Relance::class); }
}
