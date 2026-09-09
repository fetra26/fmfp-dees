<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Reserve extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'reserve';

    protected $fillable = [
        'rapport_technique_id', 'projet_id', 'description', 'niveau',
        'statut', 'echeance', 'date_levee', 'reponse_porteur', 'created_by',
    ];

    protected $casts = ['echeance' => 'date', 'date_levee' => 'date'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['statut', 'niveau'])->logOnlyDirty()->useLogName('reserve');
    }

    public function rapport() { return $this->belongsTo(RapportTechnique::class, 'rapport_technique_id'); }
    public function projet() { return $this->belongsTo(Projet::class); }
}
