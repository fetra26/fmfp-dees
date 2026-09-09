<?php

namespace App\Models;

use App\Models\Concerns\NormaliseReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Projet extends Model
{
    use SoftDeletes, LogsActivity, NormaliseReference;

    protected $table = 'projet';

    protected $fillable = [
        'reference', 'reference_normalisee', 'intitule', 'convention_id', 'statut_projet_id',
        'guichet_id', 'vague_id', 'secteur_id', 'region_id',
        'date_debut', 'date_fin', 'observations', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference', 'statut_projet_id'])
            ->logOnlyDirty()->useLogName('projet');
    }

    public function statut()        { return $this->belongsTo(StatutProjet::class, 'statut_projet_id'); }
    public function convention()    { return $this->belongsTo(Convention::class); }
    public function guichet()       { return $this->belongsTo(Guichet::class); }
    public function vague()         { return $this->belongsTo(Vague::class); }
    public function secteur()       { return $this->belongsTo(Secteur::class); }
    public function region()        { return $this->belongsTo(Region::class); }
    public function porteurProjs()  { return $this->hasMany(PorteurProj::class); }
    public function suiviTerrains() { return $this->hasMany(SuiviTerrain::class); }
    public function relances()      { return $this->hasMany(Relance::class); }
    public function rapportsTechniques() { return $this->hasMany(RapportTechnique::class); }
    public function danos()         { return $this->hasMany(Dano::class); }
}
