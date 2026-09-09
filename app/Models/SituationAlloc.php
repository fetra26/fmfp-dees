<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SituationAlloc extends Model
{
    protected $table = 'situation_alloc';
    protected $fillable = ['code', 'libelle'];
}
