<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use SoftDeletes;

    protected $table = 'module';
    protected $fillable = ['intitule', 'code', 'created_by'];

    public function formations() { return $this->belongsToMany(Formation::class, 'form_mod')->withPivot(['formateur_id', 'volume_horaire']); }
}
