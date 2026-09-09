<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prestataire extends Model
{
    use SoftDeletes;

    protected $table = 'prestataire';
    protected $fillable = ['nom', 'type', 'contact', 'created_by'];

    public function formations() { return $this->belongsToMany(Formation::class, 'presta_form'); }
}
