<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Formateur extends Model
{
    use SoftDeletes;

    protected $table = 'formateur';
    protected $fillable = ['nom', 'prenom', 'contact', 'specialite', 'created_by'];

    public function formMods() { return $this->hasMany(FormMod::class); }
}
