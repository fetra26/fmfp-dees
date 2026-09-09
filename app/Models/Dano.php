<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Dano extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'dano';

    protected $fillable = [
        'projet_id', 'type_dano_id', 'reference', 'date_dano', 'contenu', 'created_by',
    ];

    protected $casts = ['date_dano' => 'date'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['reference', 'type_dano_id'])->logOnlyDirty()->useLogName('dano');
    }

    public function projet() { return $this->belongsTo(Projet::class); }
    public function type() { return $this->belongsTo(TypeDano::class, 'type_dano_id'); }
}
