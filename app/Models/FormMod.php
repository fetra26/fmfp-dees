<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormMod extends Model
{
    protected $table = 'form_mod';
    protected $fillable = ['formation_id', 'module_id', 'formateur_id', 'volume_horaire'];

    public function formation() { return $this->belongsTo(Formation::class); }
    public function module()    { return $this->belongsTo(Module::class); }
    public function formateur() { return $this->belongsTo(Formateur::class); }
}
