<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrestaForm extends Model
{
    protected $table = 'presta_form';
    protected $fillable = ['prestataire_id', 'formation_id'];

    public function prestataire() { return $this->belongsTo(Prestataire::class); }
    public function formation()   { return $this->belongsTo(Formation::class); }
}
