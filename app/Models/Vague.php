<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vague extends Model
{
    protected $table = 'vague';
    protected $fillable = ['code', 'libelle', 'annee', 'guichet_id', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function guichet() { return $this->belongsTo(Guichet::class); }
    public function projets() { return $this->hasMany(Projet::class); }
}
